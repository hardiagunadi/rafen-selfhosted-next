<?php

namespace App\Services;

use App\Models\OltConnection;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\Pool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class HsgqSnmpCollector
{
    /**
     * OID command fields are write-only on many OLT variants, so probing via snmpwalk is unreliable.
     *
     * @var array<int, string>
     */
    private const WRITE_ONLY_MODEL_FIELDS = [
        'oid_reboot_onu',
    ];

    private const OID_SYS_DESCR = '1.3.6.1.2.1.1.1.0';

    private const OID_SYS_OBJECT_ID = '1.3.6.1.2.1.1.2.0';

    private const OID_HSGQ_TX_OLT_MILLIWATT = '1.3.6.1.4.1.50224.3.2.4.1.8';

    private const OID_HSGQ_TX_OLT_DBM = '1.3.6.1.4.1.50224.3.2.4.1.11';

    /**
     * @return array<int, string>
     */
    public static function availableModels(): array
    {
        return array_keys((array) config('olt.hsgq_models', []));
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     * @return array{
     *   matched_model: string|null,
     *   sys_descr: string|null,
     *   sys_object_id: string|null,
     *   candidate_models: array<int, string>
     * }
     */
    public function detectModelFromSnmp(array $connectionConfig): array
    {
        $tempConnection = $this->buildConnectionFromConfig($connectionConfig);

        $sysDescr = $this->readScalarValue($tempConnection, self::OID_SYS_DESCR);
        $sysObjectId = $this->readScalarValue($tempConnection, self::OID_SYS_OBJECT_ID);

        if ($sysDescr === null && $sysObjectId === null) {
            throw new RuntimeException('Gagal membaca identitas perangkat lewat SNMP. Pastikan host, port, dan community benar.');
        }

        $matchedModel = $this->matchModelFromDeviceMetadata($sysDescr, $sysObjectId);

        return [
            'matched_model' => $matchedModel,
            'sys_descr' => $sysDescr,
            'sys_object_id' => $sysObjectId,
            'candidate_models' => $this->suggestSimilarModels($sysDescr, $sysObjectId),
        ];
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     * @return array{
     *   model: string,
     *   oids: array<string, string>,
     *   probe: array<string, array{oid: string, sample_count: int, detected: bool}>,
     *   detected_fields: int
     * }
     */
    public function detectMappingFromModel(array $connectionConfig): array
    {
        $requestedModel = trim((string) ($connectionConfig['olt_model'] ?? ''));
        if ($requestedModel === '') {
            throw new RuntimeException('Model OLT belum dipilih.');
        }

        $profile = $this->resolveModelProfile($requestedModel, $connectionConfig);
        if (! is_array($profile['profile'])) {
            $suggestions = implode(', ', $profile['candidate_models']);
            throw new RuntimeException(
                'Profil OID untuk model "'.$requestedModel.'" tidak ditemukan. '.
                ($suggestions !== '' ? 'Kandidat terdekat: '.$suggestions.'. ' : '').
                'Isi OID manual jika model belum tersedia.'
            );
        }

        $resolvedModel = $profile['model'];
        $oids = (array) ($profile['profile']['oids'] ?? []);
        if (empty($oids)) {
            throw new RuntimeException('Profil OID model ini belum dikonfigurasi.');
        }

        $tempConnection = $this->buildConnectionFromConfig($connectionConfig);
        $probe = [];

        foreach ($oids as $field => $oid) {
            $oidValue = trim((string) $oid);
            if ($oidValue === '') {
                continue;
            }

            if (in_array((string) $field, self::WRITE_ONLY_MODEL_FIELDS, true)) {
                $probe[(string) $field] = [
                    'oid' => $oidValue,
                    'sample_count' => 0,
                    'detected' => false,
                ];

                continue;
            }

            $sampleCount = count($this->walkByIndex($tempConnection, $oidValue));
            $probe[(string) $field] = [
                'oid' => $oidValue,
                'sample_count' => $sampleCount,
                'detected' => $sampleCount > 0,
            ];
        }

        $detectedFields = collect($probe)
            ->where('detected', true)
            ->count();

        if ($detectedFields === 0) {
            throw new RuntimeException($this->buildOidDetectionFailureMessage($connectionConfig, $resolvedModel));
        }

        return [
            'model' => $resolvedModel,
            'oids' => $oids,
            'probe' => $probe,
            'detected_fields' => $detectedFields,
        ];
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     */
    private function buildOidDetectionFailureMessage(array $connectionConfig, string $resolvedModel): string
    {
        try {
            $detectedModelData = $this->detectModelFromSnmp($connectionConfig);
        } catch (RuntimeException) {
            return 'Tidak ada OID model yang terbaca. Pastikan host/SNMP/community dan model OLT benar.';
        }

        $detectedModel = $detectedModelData['matched_model'] ?? null;
        $sysDescr = $detectedModelData['sys_descr'] ?? null;

        if (is_string($detectedModel) && $detectedModel !== '' && $detectedModel !== $resolvedModel) {
            return 'SNMP terhubung, tetapi profil OID untuk model "'.$resolvedModel
                .'" tidak cocok dengan perangkat ini. Perangkat terdeteksi sebagai "'.$detectedModel.'".';
        }

        if (is_string($sysDescr) && $sysDescr !== '') {
            return 'SNMP terhubung ke perangkat "'.$sysDescr
                .'", tetapi profil OID untuk model "'.$resolvedModel
                .'" belum cocok. Isi OID manual atau kirim hasil snmpwalk untuk pemetaan.';
        }

        return 'SNMP terhubung, tetapi profil OID untuk model "'.$resolvedModel
            .'" belum cocok. Isi OID manual atau kirim hasil snmpwalk untuk pemetaan.';
    }

    /**
     * @param  callable(int, int, string): void|null  $progressReporter
     * @return array<int, array<string, mixed>>
     */
    public function collect(OltConnection $oltConnection, ?callable $progressReporter = null): array
    {
        $txOltOids = $this->resolveTxOltOids($oltConnection);

        $oidMap = [
            'serial_number' => $oltConnection->oid_serial,
            'onu_name' => $oltConnection->oid_onu_name,
            'distance_raw' => $oltConnection->oid_distance,
            'rx_onu_raw' => $oltConnection->oid_rx_onu,
            'tx_onu_raw' => $oltConnection->oid_tx_onu,
            'rx_olt_raw' => $oltConnection->oid_rx_olt,
            'tx_olt_raw' => $txOltOids['preferred'],
            'tx_olt_raw_fallback' => $txOltOids['fallback'],
            'status' => $oltConnection->oid_status,
        ];

        $configuredOidMap = array_filter($oidMap, fn (?string $oid) => filled($oid));
        if (empty($configuredOidMap)) {
            throw new RuntimeException('OID SNMP belum dikonfigurasi. Isi minimal satu OID pada data OLT.');
        }

        $walkResults = $this->walkConfiguredOids($oltConnection, $configuredOidMap, $progressReporter);

        $preferredIndexes = collect([
            'serial_number',
            'onu_name',
            'status',
            'distance_raw',
            'rx_onu_raw',
            'tx_onu_raw',
        ])->flatMap(fn (string $field) => array_keys($walkResults[$field] ?? []))
            ->unique()
            ->values()
            ->all();

        $allIndexes = ! empty($preferredIndexes)
            ? $preferredIndexes
            : collect($walkResults)
                ->flatMap(fn (array $valuesByIndex) => array_keys($valuesByIndex))
                ->unique()
                ->values()
                ->all();

        if (empty($allIndexes)) {
            return [];
        }

        $rows = [];
        foreach ($allIndexes as $onuIndex) {
            $ponAndOnu = $this->inferPonAndOnu($onuIndex);
            $txOltResolution = $this->resolveTxOltRawValue(
                $walkResults['tx_olt_raw'] ?? [],
                $walkResults['tx_olt_raw_fallback'] ?? [],
                $onuIndex,
                $txOltOids['preferred'],
                $txOltOids['fallback'],
            );

            $rawPayload = [
                'rx_onu' => $this->resolveWalkValue($walkResults['rx_onu_raw'] ?? [], $onuIndex),
                'tx_onu' => $this->resolveWalkValue($walkResults['tx_onu_raw'] ?? [], $onuIndex),
                'rx_olt' => $this->resolveWalkValue($walkResults['rx_olt_raw'] ?? [], $onuIndex, true),
                'tx_olt' => $txOltResolution['value'],
                'distance' => $this->resolveWalkValue($walkResults['distance_raw'] ?? [], $onuIndex),
                'status' => $this->resolveWalkValue($walkResults['status'] ?? [], $onuIndex),
            ];

            $rows[] = [
                'onu_index' => $onuIndex,
                'pon_interface' => $ponAndOnu['pon_interface'],
                'onu_number' => $ponAndOnu['onu_number'],
                'serial_number' => $walkResults['serial_number'][$onuIndex] ?? null,
                'onu_name' => $walkResults['onu_name'][$onuIndex] ?? null,
                'distance_m' => $this->parseDistanceValue($rawPayload['distance']),
                'rx_onu_dbm' => $this->parseOpticalValue($rawPayload['rx_onu'], 'rx_onu', $oltConnection),
                'tx_onu_dbm' => $this->parseOpticalValue($rawPayload['tx_onu'], 'tx_onu', $oltConnection),
                'rx_olt_dbm' => $this->parseOpticalValue($rawPayload['rx_olt'], 'rx_olt', $oltConnection),
                'tx_olt_dbm' => $this->parseOpticalValue($rawPayload['tx_olt'], 'tx_olt', $oltConnection, $txOltResolution['oid']),
                'status' => $this->normalizeOnuStatus($rawPayload['status']),
                'raw_payload' => $rawPayload,
            ];
        }

        return $rows;
    }

    /**
     * Polling cepat: fokus data penting (redaman Rx ONU + status + distance).
     *
     * @param  callable(int, int, string): void|null  $progressReporter
     * @return array<int, array<string, mixed>>
     */
    public function collectEssential(OltConnection $oltConnection, ?callable $progressReporter = null): array
    {
        $oidMap = [
            'distance_raw' => $oltConnection->oid_distance,
            'rx_onu_raw' => $oltConnection->oid_rx_onu,
            'status' => $oltConnection->oid_status,
        ];

        $configuredOidMap = array_filter($oidMap, fn (?string $oid) => filled($oid));
        if (empty($configuredOidMap)) {
            throw new RuntimeException('OID penting (Rx ONU / status / distance) belum dikonfigurasi.');
        }

        $walkResults = $this->walkConfiguredOids($oltConnection, $configuredOidMap, $progressReporter);

        $allIndexes = collect([
            'distance_raw',
            'rx_onu_raw',
            'status',
        ])->flatMap(fn (string $field) => array_keys($walkResults[$field] ?? []))
            ->unique()
            ->values()
            ->all();

        if (empty($allIndexes)) {
            return [];
        }

        $rows = [];
        foreach ($allIndexes as $onuIndex) {
            $ponAndOnu = $this->inferPonAndOnu($onuIndex);
            $rawPayload = [
                'distance' => $this->resolveWalkValue($walkResults['distance_raw'] ?? [], $onuIndex),
                'rx_onu' => $this->resolveWalkValue($walkResults['rx_onu_raw'] ?? [], $onuIndex),
                'status' => $this->resolveWalkValue($walkResults['status'] ?? [], $onuIndex),
            ];

            $rows[] = [
                'onu_index' => $onuIndex,
                'pon_interface' => $ponAndOnu['pon_interface'],
                'onu_number' => $ponAndOnu['onu_number'],
                'distance_m' => $this->parseDistanceValue($rawPayload['distance']),
                'rx_onu_dbm' => $this->parseOpticalValue($rawPayload['rx_onu'], 'rx_onu', $oltConnection),
                'status' => $this->normalizeOnuStatus($rawPayload['status']),
                'raw_payload' => $rawPayload,
            ];
        }

        return $rows;
    }

    public function rebootOnu(OltConnection $oltConnection, string $onuIndex): void
    {
        $snmpWriteCommunity = trim((string) $oltConnection->snmp_write_community);
        if ($snmpWriteCommunity === '') {
            throw new RuntimeException('SNMP Write Community belum diisi pada konfigurasi OLT.');
        }

        $rebootBaseOid = $this->normalizeOid($oltConnection->oid_reboot_onu);
        if ($rebootBaseOid === null) {
            throw new RuntimeException('OID reboot ONU belum diisi pada konfigurasi OLT.');
        }

        $normalizedOnuIndex = trim($onuIndex);
        if ($normalizedOnuIndex === '' || preg_match('/^[0-9]+(?:\.[0-9]+)*$/', $normalizedOnuIndex) !== 1) {
            throw new RuntimeException('ONU index tidak valid.');
        }

        $rebootValue = $this->resolveRebootValue($oltConnection);

        $command = sprintf(
            'snmpset -On -v2c -c %s -t %d -r %d %s %s i %d',
            escapeshellarg($snmpWriteCommunity),
            $oltConnection->snmp_timeout,
            $oltConnection->snmp_retries,
            escapeshellarg($oltConnection->host.':'.$oltConnection->snmp_port),
            escapeshellarg('.'.$rebootBaseOid.'.'.$normalizedOnuIndex),
            $rebootValue,
        );

        try {
            $result = Process::timeout($this->resolveProcessTimeoutSeconds($oltConnection))
                ->run($command);
        } catch (ProcessTimedOutException) {
            throw new RuntimeException($this->snmpTimeoutMessage($oltConnection));
        }

        if ($result->failed()) {
            $message = trim($result->errorOutput() ?: $result->output());
            throw new RuntimeException($this->normalizeSnmpSetFailureMessage($oltConnection, $message));
        }
    }

    /**
     * @return array{entries: array<int, string>, source_oids: array<int, string>, notice: string|null}
     */
    public function fetchOnuAlarmLogs(
        OltConnection $oltConnection,
        string $onuIndex,
        ?string $onuId = null,
        ?string $serialNumber = null
    ): array {
        $cloudAlarmData = $this->fetchOnuAlarmLogsFromCloud($oltConnection, $onuIndex, $onuId, $serialNumber);
        if ($cloudAlarmData !== null) {
            return $cloudAlarmData;
        }

        $configuredAlarmOids = collect((array) config('olt.alarm.oids', []))
            ->map(fn (mixed $oid): string => ltrim(trim((string) $oid), '.'))
            ->filter(fn (string $oid): bool => $oid !== '')
            ->values()
            ->all();
        $alarmOids = $configuredAlarmOids !== []
            ? $configuredAlarmOids
            : $this->resolveAlarmDiscoveryRoots($oltConnection);

        $searchTokens = $this->buildAlarmSearchTokens($onuIndex, $onuId, $serialNumber);
        $maxEntries = max(1, (int) config('olt.alarm.max_entries', 50));
        $entries = [];
        $sourceOids = [];
        $lastTimeoutMessage = null;

        foreach ($alarmOids as $alarmOid) {
            $sourceOids[] = $alarmOid;
            try {
                $lines = $this->walkRawLines($oltConnection, $alarmOid);
            } catch (RuntimeException $exception) {
                if ($this->isTimeoutErrorMessage($exception->getMessage())) {
                    $lastTimeoutMessage = $exception->getMessage();
                }

                continue;
            }

            foreach ($lines as $line) {
                $normalizedLine = strtolower($line);
                $matched = collect($searchTokens)
                    ->contains(fn (string $token): bool => $token !== '' && str_contains($normalizedLine, strtolower($token)));

                if (! $matched) {
                    continue;
                }

                $entries[] = $line;
            }
        }

        $entries = array_values(array_unique($entries));
        if ($entries === []) {
            return [
                'entries' => [],
                'source_oids' => $sourceOids,
                'notice' => $lastTimeoutMessage !== null
                    ? $this->snmpAlarmTimeoutMessage($oltConnection).' Alarm kemungkinan tidak diekspos lewat SNMP pada perangkat ini.'
                    : 'Alarm ONU tidak tersedia lewat SNMP pada perangkat ini.',
            ];
        }

        return [
            'entries' => array_slice($entries, 0, $maxEntries),
            'source_oids' => $sourceOids,
            'notice' => null,
        ];
    }

    /**
     * @return array{entries: array<int, string>, source_oids: array<int, string>, notice: string|null}|null
     */
    private function fetchOnuAlarmLogsFromCloud(
        OltConnection $oltConnection,
        string $onuIndex,
        ?string $onuId = null,
        ?string $serialNumber = null
    ): ?array {
        if (! (bool) config('olt.alarm.cloud.enabled', true)) {
            return null;
        }

        $url = trim((string) config('olt.alarm.cloud.url', ''));
        $token = trim((string) config('olt.alarm.cloud.token', ''));
        if ($url === '' || $token === '') {
            return null;
        }

        $pageSize = max(10, min(200, (int) config('olt.alarm.cloud.page_size', 50)));
        $maxPages = max(1, min(20, (int) config('olt.alarm.cloud.max_pages', 5)));
        $timeout = max(3, min(20, (int) config('olt.alarm.cloud.timeout', 8)));

        $entries = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            try {
                $response = Http::timeout($timeout)
                    ->acceptJson()
                    ->withHeaders([
                        'x-token' => $token,
                    ])
                    ->get($url, [
                        'current' => $page,
                        'page_size' => $pageSize,
                    ]);
            } catch (\Throwable) {
                return null;
            }

            if (! $response->ok()) {
                return null;
            }

            $payload = $response->json();
            if (! is_array($payload) || (int) ($payload['code'] ?? 0) !== 1) {
                return null;
            }

            $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            foreach ($rows as $row) {
                if (! is_array($row) || ! $this->isCloudAlarmRowMatch($row, $oltConnection, $onuIndex, $onuId, $serialNumber)) {
                    continue;
                }

                $entries[] = $this->formatCloudAlarmEntry($row);
            }

            if (count($rows) < $pageSize) {
                break;
            }
        }

        $entries = array_values(array_unique(array_filter($entries)));
        if ($entries === []) {
            return [
                'entries' => [],
                'source_oids' => ['hsgq-cloud'],
                'notice' => 'Alarm ONU belum ditemukan di HSGQ Cloud untuk ONU ini.',
            ];
        }

        return [
            'entries' => array_slice($entries, 0, max(1, (int) config('olt.alarm.max_entries', 50))),
            'source_oids' => ['hsgq-cloud'],
            'notice' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isCloudAlarmRowMatch(
        array $row,
        OltConnection $oltConnection,
        string $onuIndex,
        ?string $onuId = null,
        ?string $serialNumber = null
    ): bool {
        $entity = strtolower((string) ($row['entity'] ?? ''));
        $aliasName = strtolower((string) ($row['aliasname'] ?? ''));
        $location = strtolower((string) ($row['location'] ?? ''));
        $description = strtolower((string) ($row['desc'] ?? ''));

        $serialHex = strtolower((string) preg_replace('/[^a-f0-9]/i', '', (string) $serialNumber));
        if ($serialHex !== '' && $entity === $serialHex) {
            return true;
        }

        $normalizedOnuId = trim((string) $onuId);
        if ($normalizedOnuId !== '' && $normalizedOnuId !== '-') {
            $segments = explode('/', $normalizedOnuId);
            $pon = isset($segments[0]) ? (int) $segments[0] : null;
            $onu = isset($segments[1]) ? (int) $segments[1] : null;

            if ($pon !== null && $onu !== null) {
                $variants = [
                    strtolower($pon.'/'.$onu),
                    strtolower('onu '.$pon.'/'.$onu),
                    strtolower('onu '.$pon.'/'.str_pad((string) $onu, 2, '0', STR_PAD_LEFT)),
                    strtolower('onu'.str_pad((string) $pon, 2, '0', STR_PAD_LEFT).'/'.str_pad((string) $onu, 2, '0', STR_PAD_LEFT)),
                ];

                foreach ($variants as $variant) {
                    if ($variant !== '' && (str_contains($aliasName, $variant) || str_contains($location, $variant))) {
                        return true;
                    }
                }
            }
        }

        if ($onuIndex !== '' && str_contains($aliasName, strtolower($onuIndex))) {
            return true;
        }

        if ($onuId !== null && $onuId !== '-' && str_contains($description, strtolower('onu '.trim($onuId)))) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function formatCloudAlarmEntry(array $row): string
    {
        $timestamp = (int) ($row['timestamp'] ?? $row['create_time'] ?? 0);
        $dateTime = $timestamp > 0
            ? Carbon::createFromTimestamp($timestamp)->format('Y/m/d H:i:s')
            : now()->format('Y/m/d H:i:s');

        $level = match ((int) ($row['level'] ?? 0)) {
            1 => 'Warning',
            2 => 'Debug',
            default => 'Info',
        };

        $location = trim((string) ($row['location'] ?? '-'));
        $entity = trim((string) ($row['entity'] ?? '-'));
        $description = trim((string) ($row['desc'] ?? 'Alarm'));

        return '['.$dateTime.'] '.$level.': '.$location.' '.$entity.' '.$description;
    }

    /**
     * @return array<int, string>
     */
    private function resolveAlarmDiscoveryRoots(OltConnection $oltConnection): array
    {
        $configuredRoots = collect((array) config('olt.alarm.discovery_roots', []))
            ->map(fn (mixed $oid): string => ltrim(trim((string) $oid), '.'))
            ->filter(fn (string $oid): bool => $oid !== '')
            ->values()
            ->all();

        $connectionSubtreeRoots = collect([
            $oltConnection->oid_serial,
            $oltConnection->oid_onu_name,
            $oltConnection->oid_status,
            $oltConnection->oid_reboot_onu,
        ])->flatMap(fn (?string $oid): array => $this->deriveAlarmSubtreeRootsFromOid($oid))
            ->values()
            ->all();

        $enterpriseFallbackRoots = collect([
            $oltConnection->oid_serial,
            $oltConnection->oid_onu_name,
            $oltConnection->oid_status,
            $oltConnection->oid_reboot_onu,
        ])->map(fn (?string $oid): ?string => $this->extractEnterpriseRoot($oid))
            ->filter(fn (?string $root): bool => $root !== null && $root !== '')
            ->values()
            ->all();

        $resolved = array_values(array_unique(array_filter(array_merge(
            $connectionSubtreeRoots,
            $configuredRoots,
            $enterpriseFallbackRoots,
        ))));
        if ($resolved === []) {
            throw new RuntimeException('Gagal menentukan root OID alarm ONU otomatis.');
        }

        return $resolved;
    }

    /**
     * @return array<int, string>
     */
    private function deriveAlarmSubtreeRootsFromOid(?string $oid): array
    {
        $normalizedOid = $this->normalizeOid($oid);
        if ($normalizedOid === null) {
            return [];
        }

        $segments = array_values(array_filter(explode('.', $normalizedOid), fn (string $segment): bool => $segment !== ''));
        if (count($segments) < 9) {
            return [];
        }

        $candidates = [];
        $candidateSegmentSizes = [
            count($segments) - 1,
            count($segments) - 2,
            count($segments) - 3,
        ];

        foreach ($candidateSegmentSizes as $size) {
            if ($size < 8) {
                continue;
            }

            $candidates[] = implode('.', array_slice($segments, 0, $size));
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function extractEnterpriseRoot(?string $oid): ?string
    {
        $normalizedOid = $this->normalizeOid($oid);
        if ($normalizedOid === null) {
            return null;
        }

        if (str_starts_with($normalizedOid, '1.3.6.1.4.1.5875.800')) {
            return '1.3.6.1.4.1.5875.800';
        }

        if (str_starts_with($normalizedOid, '1.3.6.1.4.1.50224')) {
            return '1.3.6.1.4.1.50224';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     * @return array{
     *   model: string,
     *   profile: array<string, mixed>|null,
     *   candidate_models: array<int, string>
     * }
     */
    private function resolveModelProfile(string $requestedModel, array $connectionConfig): array
    {
        $modelProfiles = (array) config('olt.hsgq_models', []);
        $exactProfile = $modelProfiles[$requestedModel] ?? null;
        if (is_array($exactProfile)) {
            return [
                'model' => $requestedModel,
                'profile' => $exactProfile,
                'candidate_models' => [],
            ];
        }

        $detectedModelData = $this->detectModelFromSnmp($connectionConfig);
        $matchedModel = $detectedModelData['matched_model'];
        if (is_string($matchedModel) && isset($modelProfiles[$matchedModel]) && is_array($modelProfiles[$matchedModel])) {
            return [
                'model' => $matchedModel,
                'profile' => $modelProfiles[$matchedModel],
                'candidate_models' => $detectedModelData['candidate_models'],
            ];
        }

        return [
            'model' => $requestedModel,
            'profile' => null,
            'candidate_models' => $detectedModelData['candidate_models'],
        ];
    }

    private function matchModelFromDeviceMetadata(?string $sysDescr, ?string $sysObjectId): ?string
    {
        $models = self::availableModels();
        $bestMatch = null;
        $bestScore = 0;

        foreach ($models as $model) {
            $score = $this->scoreModelCandidate($model, $sysDescr, $sysObjectId);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $model;
            }
        }

        if ($bestScore < 3) {
            return null;
        }

        return $bestMatch;
    }

    /**
     * @return array<int, string>
     */
    private function suggestSimilarModels(?string $sysDescr, ?string $sysObjectId): array
    {
        $scores = [];
        foreach (self::availableModels() as $model) {
            $score = $this->scoreModelCandidate($model, $sysDescr, $sysObjectId);
            if ($score > 0) {
                $scores[$model] = $score;
            }
        }

        arsort($scores);

        return array_slice(array_keys($scores), 0, 3);
    }

    private function scoreModelCandidate(string $model, ?string $sysDescr, ?string $sysObjectId): int
    {
        $normalizedReferences = array_filter([
            $this->normalizeModelToken($sysDescr),
            $this->normalizeModelToken($sysObjectId),
        ], fn (string $value): bool => $value !== '');

        if (empty($normalizedReferences)) {
            return 0;
        }

        $tokens = $this->extractModelTokens($model);
        if (empty($tokens)) {
            return 0;
        }

        $score = 0;
        foreach ($tokens as $token) {
            foreach ($normalizedReferences as $reference) {
                if (str_contains($reference, $token)) {
                    $score += strlen($token);
                    break;
                }
            }
        }

        return $score;
    }

    /**
     * @return array<int, string>
     */
    private function extractModelTokens(string $value): array
    {
        preg_match_all('/[A-Z0-9]{3,}/', strtoupper($value), $matches);
        $tokens = array_unique($matches[0] ?? []);

        return array_values(array_filter($tokens, fn (string $token): bool => $token !== 'HSGQ'));
    }

    private function normalizeModelToken(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return preg_replace('/[^A-Z0-9]/', '', strtoupper($value)) ?? '';
    }

    private function readScalarValue(OltConnection $oltConnection, string $oid): ?string
    {
        $normalizedOid = ltrim(trim($oid), '.');

        $command = sprintf(
            'snmpwalk -On -v2c -c %s -t %d -r %d %s %s',
            escapeshellarg($oltConnection->snmp_community),
            $oltConnection->snmp_timeout,
            $oltConnection->snmp_retries,
            escapeshellarg($oltConnection->host.':'.$oltConnection->snmp_port),
            escapeshellarg('.'.$normalizedOid),
        );

        try {
            $result = Process::timeout($this->resolveProcessTimeoutSeconds($oltConnection))
                ->run($command);
        } catch (ProcessTimedOutException) {
            return null;
        }

        if ($result->failed()) {
            $error = trim($result->errorOutput() ?: $result->output());
            $normalizedError = strtolower($error);
            if (str_contains($normalizedError, 'timeout') || str_contains($normalizedError, 'no response')) {
                return null;
            }

            throw new RuntimeException('SNMP read gagal: '.$error);
        }

        return $this->parseScalarOutput($result->output(), $normalizedOid);
    }

    private function parseScalarOutput(string $output, string $oid): ?string
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($output)) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (! preg_match('/^\.?(?<oid>[0-9\.]+)\s*=\s*(?<type>[^:]+):\s*(?<value>.*)$/', $line, $matches)) {
                continue;
            }

            if (ltrim((string) $matches['oid'], '.') !== $oid) {
                continue;
            }

            return $this->normalizeText($matches['value']);
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function walkByIndex(OltConnection $oltConnection, string $baseOid): array
    {
        $normalizedBaseOid = ltrim(trim($baseOid), '.');
        $command = $this->buildSnmpWalkCommand($oltConnection, $normalizedBaseOid);

        try {
            $result = Process::timeout($this->resolveProcessTimeoutSeconds($oltConnection))
                ->run($command);
        } catch (ProcessTimedOutException) {
            throw new RuntimeException($this->snmpTimeoutMessage($oltConnection));
        }

        if ($result->failed()) {
            throw new RuntimeException($this->normalizeSnmpAlarmFailureMessage($oltConnection, trim($result->errorOutput() ?: $result->output())));
        }

        return $this->parseWalkOutput($result->output(), $normalizedBaseOid);
    }

    /**
     * @param  array<string, string>  $configuredOidMap
     * @param  callable(int, int, string): void|null  $progressReporter
     * @return array<string, array<string, string>>
     */
    private function walkConfiguredOids(
        OltConnection $oltConnection,
        array $configuredOidMap,
        ?callable $progressReporter = null
    ): array {
        $parallelBatchSize = max(1, min(8, (int) config('olt.polling.parallel_walk_batch', 3)));
        $walkResults = [];
        $oidEntries = [];
        $completed = 0;

        foreach ($configuredOidMap as $field => $baseOid) {
            $oidEntries[] = [
                'field' => (string) $field,
                'base_oid' => (string) $baseOid,
            ];
        }

        $total = count($oidEntries);

        foreach (array_chunk($oidEntries, $parallelBatchSize) as $chunk) {
            $poolTimedOut = false;

            try {
                $poolResults = Process::pool(function (Pool $pool) use ($chunk, $oltConnection): void {
                    foreach ($chunk as $entry) {
                        $pool->as($entry['field'])
                            ->timeout($this->resolveProcessTimeoutSeconds($oltConnection))
                            ->command($this->buildSnmpWalkCommand($oltConnection, $entry['base_oid']));
                    }
                })->run();
            } catch (ProcessTimedOutException) {
                $poolTimedOut = true;
                $poolResults = [];
            }

            foreach ($chunk as $entry) {
                $field = $entry['field'];
                $normalizedBaseOid = ltrim(trim($entry['base_oid']), '.');
                $values = null;

                if (! $poolTimedOut) {
                    $result = $poolResults[$field];

                    if ($result->failed()) {
                        $errorMessage = trim($result->errorOutput() ?: $result->output());
                        $normalizedError = $this->normalizeSnmpFailureMessage($oltConnection, $errorMessage);

                        if (! $this->isTimeoutErrorMessage($normalizedError)) {
                            throw new RuntimeException($normalizedError);
                        }
                    } else {
                        $values = $this->parseWalkOutput($result->output(), $normalizedBaseOid);
                    }
                }

                if ($values === null) {
                    $values = $this->walkByIndexWithTimeoutFallback($oltConnection, $normalizedBaseOid) ?? [];
                }

                $walkResults[$field] = $values;
                $completed++;

                if ($progressReporter !== null) {
                    $progressReporter($completed, max(1, $total), $field);
                }
            }
        }

        if (collect($walkResults)->every(fn (array $valuesByIndex): bool => $valuesByIndex === [])) {
            throw new RuntimeException($this->snmpTimeoutMessage($oltConnection));
        }

        return $walkResults;
    }

    /**
     * @return array<string, string>
     */
    private function parseWalkOutput(string $output, string $baseOid): array
    {
        $valuesByIndex = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($output)) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (! preg_match('/^\.?(?<oid>[0-9\.]+)\s*=\s*(?<type>[^:]+):\s*(?<value>.*)$/', $line, $matches)) {
                continue;
            }

            $resolvedOid = ltrim((string) $matches['oid'], '.');
            $baseWithDot = $baseOid.'.';
            if (! str_starts_with($resolvedOid, $baseWithDot)) {
                continue;
            }

            $index = substr($resolvedOid, strlen($baseWithDot));
            if ($index === false || $index === '') {
                continue;
            }

            $normalizedIndex = $this->normalizeWalkIndex($baseOid, $index);
            if ($normalizedIndex === null || $normalizedIndex === '') {
                continue;
            }

            $valuesByIndex[$normalizedIndex] = $this->normalizeText($matches['value']);
        }

        return $valuesByIndex;
    }

    /**
     * @return array{pon_interface: string|null, onu_number: string|null}
     */
    private function inferPonAndOnu(string $onuIndex): array
    {
        if (ctype_digit($onuIndex)) {
            $decodedIndex = $this->decodeHsgqOnuIndex((int) $onuIndex);
            if ($decodedIndex !== null) {
                return [
                    'pon_interface' => 'PON'.$decodedIndex['pon'],
                    'onu_number' => (string) $decodedIndex['onu'],
                ];
            }
        }

        $segments = array_values(array_filter(explode('.', $onuIndex), fn (string $segment) => $segment !== ''));
        $segmentCount = count($segments);

        if ($segmentCount >= 2) {
            $ponRaw = $segments[$segmentCount - 2];
            $onuRaw = $segments[$segmentCount - 1];

            // Normalize to PON-prefixed format for consistency (e.g. "1" → "PON1")
            $ponInterface = ctype_digit($ponRaw) ? 'PON'.$ponRaw : $ponRaw;

            return [
                'pon_interface' => $ponInterface,
                'onu_number' => $onuRaw,
            ];
        }

        return [
            'pon_interface' => null,
            'onu_number' => $segments[0] ?? null,
        ];
    }

    /**
     * @return array{pon: int, onu: int}|null
     */
    private function decodeHsgqOnuIndex(int $onuIndex): ?array
    {
        $relativeIndex = $onuIndex - 16777216;
        if ($relativeIndex <= 0) {
            return null;
        }

        $pon = intdiv($relativeIndex, 256);
        $onu = $relativeIndex % 256;

        if ($pon < 0 || $onu < 1) {
            return null;
        }

        return [
            'pon' => $pon,
            'onu' => $onu,
        ];
    }

    private function parseOpticalValue(
        ?string $value,
        ?string $field = null,
        ?OltConnection $oltConnection = null,
        ?string $sourceOid = null
    ): ?float {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $value);
        if (preg_match('/-?\d+(?:\.\d+)?/', $normalized, $matches) !== 1) {
            return null;
        }

        $numericValue = (float) $matches[0];
        if ((int) $numericValue === -2147483648) {
            return null;
        }

        return round($this->normalizeOpticalScale($numericValue, $field, $oltConnection, $sourceOid), 2);
    }

    private function normalizeOpticalScale(
        float $numericValue,
        ?string $field,
        ?OltConnection $oltConnection = null,
        ?string $sourceOid = null
    ): float {
        return match ($field) {
            'tx_olt' => $this->normalizeTxOltScale($numericValue, $oltConnection, $sourceOid),
            'rx_onu', 'tx_onu', 'rx_olt' => abs($numericValue) >= 100 ? $numericValue / 100 : $numericValue,
            default => abs($numericValue) >= 1000
                ? $numericValue / 1000
                : (abs($numericValue) >= 100 ? $numericValue / 100 : $numericValue),
        };
    }

    private function normalizeTxOltScale(float $numericValue, ?OltConnection $oltConnection, ?string $sourceOid = null): float
    {
        if ($this->isTxOltMilliwattEncoded($oltConnection, $sourceOid)) {
            $milliwattValue = abs($numericValue) >= 1000 ? $numericValue / 1000 : $numericValue;

            if ($milliwattValue > 0) {
                return 10 * log10($milliwattValue);
            }
        }

        if ($this->isTxOltDbmHundredthEncoded($oltConnection, $sourceOid)) {
            return abs($numericValue) >= 100 ? $numericValue / 100 : $numericValue;
        }

        return abs($numericValue) >= 100 ? $numericValue / 100 : $numericValue;
    }

    private function isTxOltMilliwattEncoded(?OltConnection $oltConnection, ?string $sourceOid = null): bool
    {
        $normalizedOid = $this->resolveTxOltSourceOid($oltConnection, $sourceOid);

        if ($normalizedOid === null) {
            return false;
        }

        return str_starts_with($normalizedOid, self::OID_HSGQ_TX_OLT_MILLIWATT);
    }

    private function isTxOltDbmHundredthEncoded(?OltConnection $oltConnection, ?string $sourceOid = null): bool
    {
        $normalizedOid = $this->resolveTxOltSourceOid($oltConnection, $sourceOid);

        if ($normalizedOid === null) {
            return false;
        }

        return str_starts_with($normalizedOid, self::OID_HSGQ_TX_OLT_DBM);
    }

    /**
     * @return array{preferred: string|null, fallback: string|null}
     */
    private function resolveTxOltOids(OltConnection $oltConnection): array
    {
        $configuredOid = $this->normalizeOid($oltConnection->oid_tx_olt);

        if ($configuredOid === null) {
            return [
                'preferred' => null,
                'fallback' => null,
            ];
        }

        if (str_starts_with($configuredOid, self::OID_HSGQ_TX_OLT_MILLIWATT)) {
            return [
                'preferred' => self::OID_HSGQ_TX_OLT_DBM,
                'fallback' => $configuredOid,
            ];
        }

        return [
            'preferred' => $configuredOid,
            'fallback' => null,
        ];
    }

    /**
     * @param  array<string, string>  $preferredValuesByIndex
     * @param  array<string, string>  $fallbackValuesByIndex
     * @return array{value: string|null, oid: string|null}
     */
    private function resolveTxOltRawValue(
        array $preferredValuesByIndex,
        array $fallbackValuesByIndex,
        string $onuIndex,
        ?string $preferredOid,
        ?string $fallbackOid
    ): array {
        $preferredValue = $this->resolveWalkValue($preferredValuesByIndex, $onuIndex, true);
        if ($preferredValue !== null) {
            return [
                'value' => $preferredValue,
                'oid' => $preferredOid,
            ];
        }

        $fallbackValue = $this->resolveWalkValue($fallbackValuesByIndex, $onuIndex, true);

        return [
            'value' => $fallbackValue,
            'oid' => $fallbackValue !== null ? $fallbackOid : $preferredOid,
        ];
    }

    private function resolveTxOltSourceOid(?OltConnection $oltConnection, ?string $sourceOid): ?string
    {
        $normalizedSourceOid = $this->normalizeOid($sourceOid);
        if ($normalizedSourceOid !== null) {
            return $normalizedSourceOid;
        }

        return $oltConnection ? $this->normalizeOid($oltConnection->oid_tx_olt) : null;
    }

    private function normalizeOid(?string $oid): ?string
    {
        if ($oid === null) {
            return null;
        }

        $normalizedOid = ltrim(trim($oid), '.');

        return $normalizedOid !== '' ? $normalizedOid : null;
    }

    private function parseDistanceValue(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (preg_match('/\d+/', $value, $matches) !== 1) {
            return null;
        }

        return (int) $matches[0];
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"')) {
            $trimmed = trim($trimmed, '"');
        }

        return trim($trimmed) === '' ? null : trim($trimmed);
    }

    private function normalizeOnuStatus(?string $value): ?string
    {
        $normalized = $this->normalizeText($value);
        if ($normalized === null) {
            return null;
        }

        return match (strtolower($normalized)) {
            '1', 'up', 'online', 'true' => 'online',
            '2', 'down', 'offline', 'false' => 'offline',
            default => $normalized,
        };
    }

    private function normalizeWalkIndex(string $baseOid, string $index): ?string
    {
        $isHsgqOpticOid = str_starts_with($baseOid, '1.3.6.1.4.1.50224.3.3.3.1.')
            || str_starts_with($baseOid, '1.3.6.1.4.1.5875.800.3.1.1.1.1.');

        if (! $isHsgqOpticOid) {
            return $index;
        }

        if (str_ends_with($index, '.65535.65535')) {
            return null;
        }

        if (str_ends_with($index, '.0.0')) {
            $segments = explode('.', $index);

            return $segments[0] !== '' ? $segments[0] : null;
        }

        return $index;
    }

    /**
     * @param  array<string, string>  $valuesByIndex
     */
    private function resolveWalkValue(array $valuesByIndex, string $onuIndex, bool $allowPonFallback = false): ?string
    {
        if (array_key_exists($onuIndex, $valuesByIndex)) {
            return $valuesByIndex[$onuIndex];
        }

        // Cross-format fallback: try alternate index encoding
        $alternateIndex = $this->resolveAlternateIndex($onuIndex);
        if ($alternateIndex !== null && array_key_exists($alternateIndex, $valuesByIndex)) {
            return $valuesByIndex[$alternateIndex];
        }

        if (! $allowPonFallback) {
            return null;
        }

        $ponIndex = $this->derivePonIndex($onuIndex);

        return $ponIndex !== null ? ($valuesByIndex[$ponIndex] ?? null) : null;
    }

    /**
     * Convert between integer-encoded HSGQ index and segmented pon.onu format.
     *
     * HSGQ integer encoding: index = 16777216 + (pon * 256) + onu
     * Segmented format: "pon.onu" (e.g. "1.3")
     */
    private function resolveAlternateIndex(string $onuIndex): ?string
    {
        // Integer → segmented
        if (ctype_digit($onuIndex)) {
            $decoded = $this->decodeHsgqOnuIndex((int) $onuIndex);
            if ($decoded !== null) {
                return $decoded['pon'].'.'.$decoded['onu'];
            }

            return null;
        }

        // Segmented → integer
        $segments = explode('.', $onuIndex);
        if (count($segments) === 2 && ctype_digit($segments[0]) && ctype_digit($segments[1])) {
            $pon = (int) $segments[0];
            $onu = (int) $segments[1];
            if ($pon >= 0 && $onu >= 1) {
                return (string) (16777216 + ($pon * 256) + $onu);
            }
        }

        return null;
    }

    private function derivePonIndex(string $onuIndex): ?string
    {
        if (! ctype_digit($onuIndex)) {
            return null;
        }

        return (string) (intdiv((int) $onuIndex, 256) * 256);
    }

    private function buildSnmpWalkCommand(OltConnection $oltConnection, string $baseOid): string
    {
        return sprintf(
            'snmpwalk -On -v2c -c %s -t %d -r %d %s %s',
            escapeshellarg($oltConnection->snmp_community),
            $oltConnection->snmp_timeout,
            $oltConnection->snmp_retries,
            escapeshellarg($oltConnection->host.':'.$oltConnection->snmp_port),
            escapeshellarg('.'.ltrim(trim($baseOid), '.')),
        );
    }

    /**
     * @return array<int, string>
     */
    private function walkRawLines(OltConnection $oltConnection, string $baseOid): array
    {
        $command = $this->buildSnmpWalkAlarmCommand($oltConnection, $baseOid);

        try {
            $result = Process::timeout($this->resolveAlarmProcessTimeoutSeconds($oltConnection))
                ->run($command);
        } catch (ProcessTimedOutException) {
            throw new RuntimeException($this->snmpAlarmTimeoutMessage($oltConnection));
        }

        if ($result->failed()) {
            throw new RuntimeException($this->normalizeSnmpFailureMessage($oltConnection, trim($result->errorOutput() ?: $result->output())));
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($result->output())) ?: [];
        $entries = [];

        foreach ($lines as $line) {
            $entry = $this->extractSnmpLineValue($line);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    private function buildSnmpWalkAlarmCommand(OltConnection $oltConnection, string $baseOid): string
    {
        return sprintf(
            'snmpwalk -On -v2c -c %s -t %d -r %d %s %s',
            escapeshellarg($oltConnection->snmp_community),
            $this->resolveAlarmSnmpTimeoutSeconds(),
            $this->resolveAlarmSnmpRetries(),
            escapeshellarg($oltConnection->host.':'.$oltConnection->snmp_port),
            escapeshellarg('.'.ltrim(trim($baseOid), '.')),
        );
    }

    private function extractSnmpLineValue(string $line): ?string
    {
        $normalizedLine = trim($line);
        if ($normalizedLine === '') {
            return null;
        }

        if (preg_match('/^\.?(?<oid>[0-9\.]+)\s*=\s*(?<type>[^:]+):\s*(?<value>.*)$/', $normalizedLine, $matches) !== 1) {
            return $normalizedLine;
        }

        return $this->normalizeText($matches['value']) ?? $normalizedLine;
    }

    /**
     * @return array<int, string>
     */
    private function buildAlarmSearchTokens(string $onuIndex, ?string $onuId = null, ?string $serialNumber = null): array
    {
        $tokens = [trim($onuIndex)];

        $normalizedOnuId = trim((string) $onuId);
        if ($normalizedOnuId !== '' && $normalizedOnuId !== '-') {
            $tokens[] = $normalizedOnuId;
            $tokens[] = 'onu '.$normalizedOnuId;
            $tokens[] = 'onu'.$normalizedOnuId;
        }

        $normalizedSerial = trim((string) $serialNumber);
        if ($normalizedSerial !== '' && $normalizedSerial !== '-') {
            $hexSerial = strtolower((string) preg_replace('/[^a-f0-9]/i', '', $normalizedSerial));
            if (strlen($hexSerial) === 12) {
                $tokens[] = implode(':', str_split($hexSerial, 2));
                $tokens[] = $hexSerial;
            }

            $tokens[] = strtolower($normalizedSerial);
        }

        return array_values(array_unique(array_filter($tokens, fn (string $token): bool => $token !== '')));
    }

    private function resolveProcessTimeoutSeconds(OltConnection $oltConnection): int
    {
        $snmpTimeout = max(1, (int) $oltConnection->snmp_timeout);
        $snmpRetries = max(0, (int) $oltConnection->snmp_retries);

        return max(8, ($snmpTimeout * ($snmpRetries + 1)) + 3);
    }

    private function resolveAlarmProcessTimeoutSeconds(OltConnection $oltConnection): int
    {
        $snmpTimeout = max(1, min(5, $this->resolveAlarmSnmpTimeoutSeconds()));
        $snmpRetries = max(0, min(2, $this->resolveAlarmSnmpRetries()));

        return max(4, ($snmpTimeout * ($snmpRetries + 1)) + 2);
    }

    private function resolveAlarmSnmpTimeoutSeconds(): int
    {
        return max(1, (int) config('olt.alarm.snmp_timeout', 2));
    }

    private function resolveAlarmSnmpRetries(): int
    {
        return max(0, (int) config('olt.alarm.snmp_retries', 0));
    }

    private function snmpTimeoutMessage(OltConnection $oltConnection): string
    {
        return 'SNMP timeout ke OLT. Tidak ada respons dalam '.$this->resolveProcessTimeoutSeconds($oltConnection).' detik.';
    }

    private function snmpAlarmTimeoutMessage(OltConnection $oltConnection): string
    {
        return 'SNMP timeout ke OLT. Tidak ada respons dalam '.$this->resolveAlarmProcessTimeoutSeconds($oltConnection).' detik.';
    }

    private function normalizeSnmpFailureMessage(OltConnection $oltConnection, string $message): string
    {
        $normalizedMessage = strtolower($message);

        if (
            str_contains($normalizedMessage, 'timed out')
            || str_contains($normalizedMessage, 'exceeded the timeout')
            || str_contains($normalizedMessage, 'timeout')
            || str_contains($normalizedMessage, 'no response')
        ) {
            return $this->snmpTimeoutMessage($oltConnection);
        }

        return 'SNMP walk gagal: '.$message;
    }

    private function resolveRebootValue(OltConnection $oltConnection): int
    {
        return 1;
    }

    private function normalizeSnmpSetFailureMessage(OltConnection $oltConnection, string $message): string
    {
        $normalizedMessage = strtolower($message);

        if (
            str_contains($normalizedMessage, 'timed out')
            || str_contains($normalizedMessage, 'exceeded the timeout')
            || str_contains($normalizedMessage, 'timeout')
            || str_contains($normalizedMessage, 'no response')
        ) {
            return $this->snmpTimeoutMessage($oltConnection);
        }

        if (str_contains($normalizedMessage, 'wrongvalue') || str_contains($normalizedMessage, 'wrong value')) {
            return 'Reboot ONU tidak didukung oleh OLT ini (OID reboot tidak menerima nilai yang dikirim). Periksa konfigurasi OID Reboot ONU.';
        }

        if (str_contains($normalizedMessage, 'generror') || str_contains($normalizedMessage, 'general failure')) {
            return 'OLT menolak perintah reboot ONU. OID reboot mungkin tidak kompatibel dengan model OLT ini.';
        }

        return 'SNMP set gagal: '.$message;
    }

    private function normalizeSnmpAlarmFailureMessage(OltConnection $oltConnection, string $message): string
    {
        $normalizedMessage = strtolower($message);

        if (
            str_contains($normalizedMessage, 'timed out')
            || str_contains($normalizedMessage, 'exceeded the timeout')
            || str_contains($normalizedMessage, 'timeout')
            || str_contains($normalizedMessage, 'no response')
        ) {
            return $this->snmpAlarmTimeoutMessage($oltConnection);
        }

        return 'SNMP walk alarm gagal: '.$message;
    }

    private function walkByIndexWithTimeoutFallback(OltConnection $oltConnection, string $baseOid): ?array
    {
        try {
            return $this->walkByIndex($oltConnection, $baseOid);
        } catch (RuntimeException $exception) {
            if ($this->isTimeoutErrorMessage($exception->getMessage())) {
                return null;
            }

            throw $exception;
        }
    }

    private function isTimeoutErrorMessage(string $message): bool
    {
        $normalized = strtolower($message);

        return str_contains($normalized, 'timeout')
            || str_contains($normalized, 'timed out')
            || str_contains($normalized, 'exceeded the timeout')
            || str_contains($normalized, 'no response');
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     */
    private function buildConnectionFromConfig(array $connectionConfig): OltConnection
    {
        $oltConnection = new OltConnection;
        $oltConnection->host = (string) ($connectionConfig['host'] ?? '');
        $oltConnection->snmp_port = (int) ($connectionConfig['snmp_port'] ?? 161);
        $oltConnection->snmp_version = (string) ($connectionConfig['snmp_version'] ?? '2c');
        $oltConnection->snmp_community = (string) ($connectionConfig['snmp_community'] ?? '');
        $oltConnection->snmp_timeout = (int) ($connectionConfig['snmp_timeout'] ?? 5);
        $oltConnection->snmp_retries = (int) ($connectionConfig['snmp_retries'] ?? 1);

        return $oltConnection;
    }
}
