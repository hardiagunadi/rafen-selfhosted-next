<?php

namespace App\Jobs;

use App\Models\OltConnection;
use App\Models\OltOnuOptic;
use App\Models\OltOnuOpticHistory;
use App\Services\HsgqSnmpCollector;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class PollOltConnectionJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const MODE_FULL = 'full';

    public const MODE_QUICK = 'quick';

    public int $tries = 1;

    public int $uniqueFor;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $oltConnectionId, public string $mode = self::MODE_FULL)
    {
        if (! in_array($this->mode, [self::MODE_FULL, self::MODE_QUICK], true)) {
            $this->mode = self::MODE_FULL;
        }

        $this->uniqueFor = max(30, (int) config('olt.polling.lock_seconds', 900));
        $this->onQueue((string) config('olt.polling.queue', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(HsgqSnmpCollector $collector): void
    {
        $oltConnection = OltConnection::query()->find($this->oltConnectionId);

        if (! $oltConnection) {
            return;
        }

        $lockSeconds = max(30, (int) config('olt.polling.lock_seconds', 900));
        $lock = Cache::lock($this->lockKey($oltConnection->id), $lockSeconds);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->updateRunningProgress($oltConnection, 5, $this->mode === self::MODE_QUICK ? 'Quick polling: menghubungi OLT...' : 'Menghubungi OLT...');

            if (! $oltConnection->is_active) {
                $oltConnection->update([
                    'last_polled_at' => now(),
                    'last_poll_success' => false,
                    'last_poll_message' => 'Polling dilewati karena koneksi OLT nonaktif.',
                ]);

                return;
            }

            $records = $this->mode === self::MODE_QUICK
                ? $collector->collectEssential(
                    $oltConnection,
                    function (int $completed, int $total, string $field) use ($oltConnection): void {
                        $progressPercent = 10 + (int) floor(($completed / max(1, $total)) * 75);

                        $this->updateRunningProgress(
                            $oltConnection,
                            $progressPercent,
                            'Quick polling: membaca '.$this->humanizeField($field).' ('.$completed.'/'.$total.')'
                        );
                    }
                )
                : $collector->collect(
                    $oltConnection,
                    function (int $completed, int $total, string $field) use ($oltConnection): void {
                        $progressPercent = 10 + (int) floor(($completed / max(1, $total)) * 75);

                        $this->updateRunningProgress(
                            $oltConnection,
                            $progressPercent,
                            'Membaca '.$this->humanizeField($field).' ('.$completed.'/'.$total.')'
                        );
                    }
                );

            $this->updateRunningProgress($oltConnection, 90, 'Menyimpan hasil polling...');
            $now = now();

            if (! empty($records)) {
                $payload = array_map(function (array $record) use ($oltConnection, $now): array {
                    return [
                        'olt_connection_id' => $oltConnection->id,
                        'owner_id' => $oltConnection->owner_id,
                        'onu_index' => (string) $record['onu_index'],
                        'pon_interface' => $record['pon_interface'] ?? null,
                        'onu_number' => $record['onu_number'] ?? null,
                        'serial_number' => $record['serial_number'] ?? null,
                        'onu_name' => $record['onu_name'] ?? null,
                        'distance_m' => $record['distance_m'] ?? null,
                        'rx_onu_dbm' => $record['rx_onu_dbm'] ?? null,
                        'tx_onu_dbm' => $record['tx_onu_dbm'] ?? null,
                        'rx_olt_dbm' => $record['rx_olt_dbm'] ?? null,
                        'tx_olt_dbm' => $record['tx_olt_dbm'] ?? null,
                        'status' => $record['status'] ?? null,
                        'raw_payload' => is_array($record['raw_payload'] ?? null)
                            ? json_encode($record['raw_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                            : ($record['raw_payload'] ?? null),
                        'last_seen_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $records);

                OltOnuOptic::query()->upsert(
                    $payload,
                    ['olt_connection_id', 'onu_index'],
                    $this->upsertColumns()
                );

                // Insert riwayat redaman per ONU
                $onuIds = OltOnuOptic::query()
                    ->where('olt_connection_id', $oltConnection->id)
                    ->whereIn('onu_index', array_column($records, 'onu_index'))
                    ->pluck('id', 'onu_index');

                $historyPayload = [];
                foreach ($records as $record) {
                    $onuId = $onuIds[(string) $record['onu_index']] ?? null;
                    if (! $onuId) {
                        continue;
                    }
                    $historyPayload[] = [
                        'olt_onu_optic_id'  => $onuId,
                        'olt_connection_id' => $oltConnection->id,
                        'owner_id'          => $oltConnection->owner_id,
                        'rx_onu_dbm'        => $record['rx_onu_dbm'] ?? null,
                        'tx_onu_dbm'        => $record['tx_onu_dbm'] ?? null,
                        'rx_olt_dbm'        => $record['rx_olt_dbm'] ?? null,
                        'distance_m'        => $record['distance_m'] ?? null,
                        'status'            => $record['status'] ?? null,
                        'polled_at'         => $now,
                    ];
                }

                if (! empty($historyPayload)) {
                    OltOnuOpticHistory::query()->insert($historyPayload);
                }

                // Hapus history lebih dari 7 hari agar tabel tidak membengkak
                OltOnuOpticHistory::query()
                    ->where('olt_connection_id', $oltConnection->id)
                    ->where('polled_at', '<', $now->copy()->subDays(7))
                    ->delete();
            }

            $oltConnection->update([
                'last_polled_at' => $now,
                'last_poll_success' => true,
                'last_poll_message' => ($this->mode === self::MODE_QUICK ? 'Quick polling SNMP berhasil.' : 'Polling SNMP berhasil.')
                    .' ONU terdeteksi: '.count($records),
            ]);
        } catch (Throwable $exception) {
            $oltConnection->update([
                'last_polled_at' => now(),
                'last_poll_success' => false,
                'last_poll_message' => $this->normalizeFailureMessage($exception, $oltConnection),
            ]);
        } finally {
            $lock->release();
        }
    }

    private function lockKey(int $oltConnectionId): string
    {
        return 'olt-poll:'.$oltConnectionId;
    }

    public function uniqueId(): string
    {
        return (string) $this->oltConnectionId;
    }

    /**
     * @return array<int, string>
     */
    private function upsertColumns(): array
    {
        if ($this->mode === self::MODE_QUICK) {
            return [
                'pon_interface',
                'onu_number',
                'distance_m',
                'rx_onu_dbm',
                'status',
                'raw_payload',
                'last_seen_at',
                'updated_at',
            ];
        }

        return [
            'pon_interface',
            'onu_number',
            'serial_number',
            'onu_name',
            'distance_m',
            'rx_onu_dbm',
            'tx_onu_dbm',
            'rx_olt_dbm',
            'tx_olt_dbm',
            'status',
            'raw_payload',
            'last_seen_at',
            'updated_at',
        ];
    }

    private function updateRunningProgress(OltConnection $oltConnection, int $progressPercent, string $message): void
    {
        $normalizedProgress = max(0, min(99, $progressPercent));

        $oltConnection->update([
            'last_poll_success' => null,
            'last_poll_message' => OltConnection::POLLING_RUNNING_PREFIX.' '.$normalizedProgress.'% '.$message,
        ]);
    }

    private function humanizeField(string $field): string
    {
        return match ($field) {
            'serial_number' => 'OID serial ONU',
            'onu_name' => 'OID nama ONU',
            'distance_raw' => 'OID distance',
            'rx_onu_raw' => 'OID Rx ONU',
            'tx_onu_raw' => 'OID Tx ONU',
            'rx_olt_raw' => 'OID Rx OLT',
            'tx_olt_raw' => 'OID Tx OLT',
            'tx_olt_raw_fallback' => 'OID Tx OLT cadangan',
            'status' => 'OID status',
            default => str_replace('_', ' ', $field),
        };
    }

    private function normalizeFailureMessage(Throwable $exception, OltConnection $oltConnection): string
    {
        $message = trim($exception->getMessage());
        $normalizedMessage = strtolower($message);

        if (
            str_contains($normalizedMessage, 'timed out')
            || str_contains($normalizedMessage, 'exceeded the timeout')
            || str_contains($normalizedMessage, 'timeout')
            || str_contains($normalizedMessage, 'no response')
        ) {
            $timeoutSeconds = max(8, ((int) $oltConnection->snmp_timeout * ((int) $oltConnection->snmp_retries + 1)) + 3);

            return 'SNMP timeout ke OLT. Tidak ada respons dalam '.$timeoutSeconds.' detik.';
        }

        return Str::limit($message, 230);
    }
}
