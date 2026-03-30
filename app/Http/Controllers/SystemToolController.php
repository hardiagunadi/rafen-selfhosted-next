<?php

namespace App\Http\Controllers;

use App\Models\HotspotUser;
use App\Models\Invoice;
use App\Models\PppProfile;
use App\Models\PppUser;
use App\Services\HotspotRadiusSynchronizer;
use App\Services\RadiusReplySynchronizer;
use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SystemToolController extends Controller
{
    use LogsActivity;

    // ─── Cek Pemakaian ───────────────────────────────────────────────────────

    public function usageIndex(): View
    {
        return view('system_tools.usage');
    }

    public function usageData(Request $request): JsonResponse
    {
        $user = $request->user();
        $search = $request->input('search', '');
        $type = $request->input('type', 'ppp'); // ppp | hotspot

        if ($type === 'hotspot') {
            $query = HotspotUser::query()
                ->accessibleBy($user)
                ->when($search !== '', fn ($q) => $q->where(function ($q2) use ($search) {
                    $q2->where('username', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%");
                }));

            $rows = $query->orderBy('customer_name')->get()->map(function (HotspotUser $u) {
                $acct = DB::table('radacct')
                    ->where('username', $u->username)
                    ->orderByDesc('acctstarttime')
                    ->first();

                return [
                    'username' => $u->username ?? '-',
                    'customer_name' => $u->customer_name,
                    'upload' => $acct ? $this->formatBytes((int) $acct->acctinputoctets) : '-',
                    'download' => $acct ? $this->formatBytes((int) $acct->acctoutputoctets) : '-',
                    'session_time' => $acct ? $this->formatDuration((int) $acct->acctsessiontime) : '-',
                    'last_seen' => $acct?->acctstoptime ?? $acct?->acctupdatetime ?? '-',
                    'ip_address' => $acct?->framedipaddress ?? '-',
                    'online' => $acct && ! $acct->acctstoptime ? true : false,
                ];
            });
        } else {
            $query = PppUser::query()
                ->accessibleBy($user)
                ->when($search !== '', fn ($q) => $q->where(function ($q2) use ($search) {
                    $q2->where('username', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%");
                }));

            $rows = $query->orderBy('customer_name')->get()->map(function (PppUser $u) {
                $acct = DB::table('radacct')
                    ->where('username', $u->username)
                    ->orderByDesc('acctstarttime')
                    ->first();

                return [
                    'username' => $u->username ?? '-',
                    'customer_name' => $u->customer_name,
                    'upload' => $acct ? $this->formatBytes((int) $acct->acctinputoctets) : '-',
                    'download' => $acct ? $this->formatBytes((int) $acct->acctoutputoctets) : '-',
                    'session_time' => $acct ? $this->formatDuration((int) $acct->acctsessiontime) : '-',
                    'last_seen' => $acct?->acctstoptime ?? $acct?->acctupdatetime ?? '-',
                    'ip_address' => $acct?->framedipaddress ?? '-',
                    'online' => $acct && ! $acct->acctstoptime ? true : false,
                ];
            });
        }

        return response()->json(['data' => $rows]);
    }

    // ─── Impor User ──────────────────────────────────────────────────────────

    public function importIndex(): View
    {
        return view('system_tools.import');
    }

    public function importTemplate(string $type): Response
    {
        $pppHeaders = ['customer_id', 'customer_name', 'nik', 'nomor_hp', 'email', 'alamat', 'username', 'ppp_password', 'status_akun', 'status_bayar', 'jatuh_tempo', 'tipe_service', 'catatan'];
        $hotspotHeaders = ['customer_id', 'customer_name', 'nik', 'nomor_hp', 'email', 'alamat', 'username', 'hotspot_password', 'status_akun', 'status_bayar', 'jatuh_tempo', 'catatan'];
        $headers = $type === 'hotspot' ? $hotspotHeaders : $pppHeaders;
        $filename = "template_{$type}_users.csv";

        $output = fopen('php://output', 'w');
        ob_start();
        fputcsv($output, $headers);
        fclose($output);
        $csv = ob_get_clean();

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Preview CSV: kembalikan daftar new, conflict, identical.
     */
    public function importPreview(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:ppp,hotspot',
            'file' => 'required|file|max:5120',
        ]);

        $user = $request->user();
        $type = $request->input('type');
        $file = $request->file('file');

        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'txt'])) {
            return response()->json(['error' => 'File harus berformat CSV atau TXT.'], 422);
        }

        $handle = fopen($file->getRealPath(), 'r');
        $headers = array_map('trim', fgetcsv($handle) ?: []);
        $isMixRadius = in_array('Login', $headers) && in_array('FullName', $headers);

        $ownerId = $user->effectiveOwnerId();
        $newRows = [];
        $conflicts = [];
        $identical = 0;
        $parseErrors = [];
        $rowNum = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (empty(array_filter($line))) {
                continue;
            }

            $raw = array_combine($headers, array_map('trim', $line));
            if ($raw === false) {
                $parseErrors[] = "Baris {$rowNum}: kolom tidak sesuai.";

                continue;
            }

            $raw['owner_id'] = $ownerId;

            try {
                if ($isMixRadius && $type === 'ppp') {
                    $normalized = $this->normalizePppRowMixRadius($raw);
                } elseif ($type === 'ppp') {
                    $normalized = $this->normalizePppRow($raw);
                } else {
                    $normalized = $this->normalizeHotspotRow($raw);
                }
            } catch (\Throwable $e) {
                $parseErrors[] = "Baris {$rowNum}: ".$e->getMessage();

                continue;
            }

            $username = $normalized['username'];

            if ($type === 'ppp') {
                $existing = PppUser::where('username', $username)->where('owner_id', $ownerId)->first();
            } else {
                $existing = HotspotUser::where('username', $username)->where('owner_id', $ownerId)->first();
            }

            if (! $existing) {
                $newRows[] = $normalized;
            } else {
                $diff = $this->diffUserData($existing, $normalized, $type);
                if (empty($diff)) {
                    $identical++;
                } else {
                    $conflicts[] = [
                        'username' => $username,
                        'existing' => $this->summarizeUser($existing, $type),
                        'incoming' => $this->summarizeNormalized($normalized, $type),
                        'diff' => $diff,
                        '_data' => $normalized,
                    ];
                }
            }
        }

        fclose($handle);

        return response()->json([
            'type' => $type,
            'is_mixradius' => $isMixRadius,
            'new' => $newRows,
            'conflicts' => $conflicts,
            'identical' => $identical,
            'parse_errors' => $parseErrors,
        ]);
    }

    /**
     * Confirm import: insert new rows + update selected conflicts.
     */
    public function importConfirm(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:ppp,hotspot',
            'new' => 'nullable|array',
            'updates' => 'nullable|array',
        ]);

        $user = $request->user();
        $type = $request->input('type');
        $ownerId = $user->effectiveOwnerId();
        $newRows = $request->input('new', []);
        $updates = $request->input('updates', []);

        $inserted = 0;
        $updated = 0;
        $errors = [];
        $syncUsernames = [];

        foreach ($newRows as $row) {
            try {
                $row['owner_id'] = $ownerId;
                if ($type === 'ppp') {
                    PppUser::create($row);
                } else {
                    HotspotUser::create($row);
                }
                $syncUsernames[] = $row['username'];
                $inserted++;
            } catch (\Throwable $e) {
                $errors[] = "Insert '{$row['username']}': ".$e->getMessage();
            }
        }

        foreach ($updates as $row) {
            try {
                $row['owner_id'] = $ownerId;
                $username = $row['username'] ?? '';
                if (! $username) {
                    continue;
                }

                if ($type === 'ppp') {
                    PppUser::where('username', $username)->where('owner_id', $ownerId)->update($row);
                } else {
                    HotspotUser::where('username', $username)->where('owner_id', $ownerId)->update($row);
                }
                $syncUsernames[] = $username;
                $updated++;
            } catch (\Throwable $e) {
                $errors[] = "Update '{$row['username']}': ".$e->getMessage();
            }
        }

        // Sync imported users to RADIUS
        if (! empty($syncUsernames)) {
            if ($type === 'ppp') {
                $pppSynchronizer = app(RadiusReplySynchronizer::class);
                PppUser::whereIn('username', $syncUsernames)->where('owner_id', $ownerId)->get()
                    ->each(fn ($u) => $pppSynchronizer->syncSingleUser($u));
            } else {
                $hotspotSynchronizer = app(HotspotRadiusSynchronizer::class);
                HotspotUser::whereIn('username', $syncUsernames)->where('owner_id', $ownerId)->get()
                    ->each(fn ($u) => $hotspotSynchronizer->syncSingleUser($u));
            }
        }

        try {
            $this->logActivity('imported', ucfirst($type).'User', 0, "{$inserted} inserted, {$updated} updated", $ownerId);
        } catch (\Throwable $e) {
            \Log::warning('logActivity failed on import: '.$e->getMessage());
        }

        return response()->json([
            'inserted' => $inserted,
            'updated' => $updated,
            'errors' => $errors,
        ]);
    }

    // ─── Ekspor User ─────────────────────────────────────────────────────────

    public function exportUsersIndex(): View
    {
        return view('system_tools.export_users');
    }

    public function exportUsersDownload(Request $request): Response
    {
        $request->validate([
            'type' => 'required|in:ppp,hotspot',
            'status' => 'nullable|string',
        ]);

        $user = $request->user();
        $type = $request->input('type');
        $status = $request->input('status');

        if ($type === 'hotspot') {
            $rows = HotspotUser::query()
                ->accessibleBy($user)
                ->when($status, fn ($q) => $q->where('status_akun', $status))
                ->orderBy('customer_name')
                ->get();

            $headers = ['customer_id', 'customer_name', 'nik', 'nomor_hp', 'email', 'alamat', 'username', 'hotspot_password', 'status_akun', 'status_bayar', 'jatuh_tempo', 'catatan'];
        } else {
            $rows = PppUser::query()
                ->accessibleBy($user)
                ->when($status, fn ($q) => $q->where('status_akun', $status))
                ->orderBy('customer_name')
                ->get();

            $headers = ['customer_id', 'customer_name', 'nik', 'nomor_hp', 'email', 'alamat', 'username', 'ppp_password', 'status_akun', 'status_bayar', 'jatuh_tempo', 'tipe_service', 'catatan'];
        }

        $filename = "export_{$type}_users_".now()->format('Ymd_His').'.csv';

        $output = fopen('php://output', 'w');
        ob_start();

        fputcsv($output, $headers);
        foreach ($rows as $r) {
            $row = [];
            foreach ($headers as $col) {
                $val = $r->$col ?? '';
                if ($val instanceof \Carbon\Carbon) {
                    $val = $val->format('Y-m-d');
                }
                $row[] = (string) $val;
            }
            fputcsv($output, $row);
        }

        fclose($output);
        $csv = ob_get_clean();

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ─── Ekspor Transaksi ────────────────────────────────────────────────────

    public function exportTransactionsIndex(): View
    {
        return view('system_tools.export_transactions');
    }

    public function exportTransactionsDownload(Request $request): Response
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'status' => 'nullable|in:paid,unpaid',
            'format' => 'nullable|in:csv,excel',
        ]);

        $user = $request->user();
        $dateFrom = $request->input('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : null;
        $dateTo = $request->input('date_to') ? Carbon::parse($request->input('date_to'))->endOfDay() : null;
        $status = $request->input('status');
        $format = $request->input('format', 'csv');

        $rows = Invoice::query()
            ->with('owner')
            ->accessibleBy($user)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo))
            ->orderByDesc('created_at')
            ->get();

        $headers = ['invoice_number', 'customer_id', 'customer_name', 'tipe_service', 'paket_langganan', 'harga_dasar', 'ppn_percent', 'ppn_amount', 'total', 'status', 'due_date', 'paid_at', 'payment_method', 'created_at'];
        $exportRows = $rows->map(fn (Invoice $invoice) => [
            $invoice->invoice_number,
            $invoice->customer_id ?? '',
            $invoice->customer_name ?? '',
            $invoice->tipe_service ?? '',
            $invoice->paket_langganan ?? '',
            (string) $invoice->harga_dasar,
            (string) $invoice->ppn_percent,
            (string) $invoice->ppn_amount,
            (string) $invoice->total,
            $invoice->status,
            $invoice->due_date?->format('Y-m-d') ?? '',
            $invoice->paid_at?->format('Y-m-d H:i:s') ?? '',
            $invoice->payment_method ?? '',
            $invoice->created_at->format('Y-m-d H:i:s'),
        ])->all();

        if ($format === 'excel') {
            $filename = 'export_transaksi_'.now()->format('Ymd_His').'.xls';

            return $this->excelExportResponse($filename, 'Transaksi', $headers, $exportRows);
        }

        $filename = 'export_transaksi_'.now()->format('Ymd_His').'.csv';

        return $this->csvExportResponse($filename, $headers, $exportRows);
    }

    private function csvExportResponse(string $filename, array $headers, array $rows): Response
    {
        $output = fopen('php://output', 'w');
        ob_start();

        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        $csv = ob_get_clean();

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function excelExportResponse(string $filename, string $sheetName, array $headers, array $rows): Response
    {
        $excel = $this->buildSpreadsheetXml($sheetName, $headers, $rows);

        return response($excel, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function buildSpreadsheetXml(string $sheetName, array $headers, array $rows): string
    {
        $safeSheetName = preg_replace('/[\\\\\\/?*\\[\\]:]/', '-', $sheetName) ?: 'Sheet1';
        $safeSheetName = substr($safeSheetName, 0, 31);
        $sheetNameCell = htmlspecialchars($safeSheetName, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        $xmlRows = '<Row>';
        foreach ($headers as $header) {
            $xmlRows .= '<Cell><Data ss:Type="String">'.$this->escapeSpreadsheetCellValue($header).'</Data></Cell>';
        }
        $xmlRows .= '</Row>';

        foreach ($rows as $row) {
            $xmlRows .= '<Row>';
            foreach ($row as $cell) {
                $xmlRows .= '<Cell><Data ss:Type="String">'.$this->escapeSpreadsheetCellValue($cell).'</Data></Cell>';
            }
            $xmlRows .= '</Row>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<?mso-application progid="Excel.Sheet"?>'
            .'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
            .' xmlns:o="urn:schemas-microsoft-com:office:office"'
            .' xmlns:x="urn:schemas-microsoft-com:office:excel"'
            .' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
            .' xmlns:html="http://www.w3.org/TR/REC-html40">'
            .'<Worksheet ss:Name="'.$sheetNameCell.'"><Table>'.$xmlRows.'</Table></Worksheet>'
            .'</Workbook>';
    }

    private function escapeSpreadsheetCellValue(mixed $value): string
    {
        $stringValue = (string) ($value ?? '');

        if (preg_match('/^[=+\\-@]/', $stringValue) === 1) {
            $stringValue = "'".$stringValue;
        }

        return htmlspecialchars($stringValue, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    // ─── Backup & Restore DB ─────────────────────────────────────────────────

    public function backupIndex(): View
    {
        $this->requireSuperAdmin();

        $files = collect(Storage::disk('local')->files('backups'))
            ->filter(fn ($f) => str_ends_with($f, '.sql.gz'))
            ->map(fn ($f) => [
                'name' => basename($f),
                'path' => $f,
                'size' => $this->formatBytes(Storage::disk('local')->size($f)),
                'modified' => Carbon::createFromTimestamp(Storage::disk('local')->lastModified($f))->format('Y-m-d H:i:s'),
            ])
            ->sortByDesc('modified')
            ->values();

        return view('system_tools.backup', compact('files'));
    }

    public function backupDownload(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->requireSuperAdmin();

        $filename = $request->input('file');
        $path = 'backups/'.basename($filename);

        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'File backup tidak ditemukan.');
        }

        return Storage::disk('local')->download($path);
    }

    public function backupCreate(): JsonResponse
    {
        $this->requireSuperAdmin();

        $dbName = config('database.connections.mariadb.database', config('database.connections.mysql.database'));
        $dbUser = config('database.connections.mariadb.username', config('database.connections.mysql.username'));
        $dbPass = config('database.connections.mariadb.password', config('database.connections.mysql.password'));
        $dbHost = config('database.connections.mariadb.host', config('database.connections.mysql.host', '127.0.0.1'));

        $backupDir = storage_path('app/backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = 'backup_'.now()->format('Ymd_His').'.sql.gz';
        $path = $backupDir.'/'.$filename;

        $cmd = sprintf(
            'mysqldump --host=%s --user=%s --password=%s --single-transaction --routines %s | gzip > %s 2>&1',
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbName),
            escapeshellarg($path)
        );

        exec($cmd, $output, $code);

        if ($code !== 0 || ! file_exists($path) || filesize($path) < 100) {
            return response()->json(['error' => 'Backup gagal. Periksa konfigurasi database.'], 500);
        }

        $this->logActivity('backup_created', 'Database', 0, $filename, auth()->id());

        return response()->json(['status' => 'Backup berhasil dibuat.', 'file' => $filename]);
    }

    public function backupRestore(Request $request): JsonResponse
    {
        $this->requireSuperAdmin();

        $request->validate([
            'file' => 'required|file|mimes:gz|max:102400',
        ]);

        $dbName = config('database.connections.mariadb.database', config('database.connections.mysql.database'));
        $dbUser = config('database.connections.mariadb.username', config('database.connections.mysql.username'));
        $dbPass = config('database.connections.mariadb.password', config('database.connections.mysql.password'));
        $dbHost = config('database.connections.mariadb.host', config('database.connections.mysql.host', '127.0.0.1'));

        $uploadedFile = $request->file('file');
        $tmpPath = $uploadedFile->getRealPath();

        $cmd = sprintf(
            'gunzip -c %s | mysql --host=%s --user=%s --password=%s %s 2>&1',
            escapeshellarg($tmpPath),
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbName)
        );

        exec($cmd, $output, $code);

        if ($code !== 0) {
            return response()->json(['error' => 'Restore gagal: '.implode(' ', $output)], 500);
        }

        $this->logActivity('backup_restored', 'Database', 0, $uploadedFile->getClientOriginalName(), auth()->id());

        return response()->json(['status' => 'Database berhasil direstore.']);
    }

    public function backupDelete(Request $request): JsonResponse
    {
        $this->requireSuperAdmin();

        $filename = $request->input('file');
        $path = 'backups/'.basename($filename);

        Storage::disk('local')->delete($path);

        return response()->json(['status' => 'File backup dihapus.']);
    }

    // ─── Reset Laporan ───────────────────────────────────────────────────────

    public function resetReportIndex(): View
    {
        $this->requireSuperAdmin();

        return view('system_tools.reset_report');
    }

    public function resetReportExecute(Request $request): JsonResponse
    {
        $this->requireSuperAdmin();

        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2099',
        ]);

        $month = (int) $request->input('month');
        $year = (int) $request->input('year');

        $deleted = Invoice::whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->delete();

        $this->logActivity('reset_report', 'Invoice', 0, "{$year}-{$month} ({$deleted} records)", auth()->id());

        return response()->json(['status' => "Reset laporan {$year}-{$month} berhasil. {$deleted} invoice dihapus."]);
    }

    // ─── Reset Database ──────────────────────────────────────────────────────

    public function resetDatabaseIndex(): View
    {
        $this->requireSuperAdmin();

        return view('system_tools.reset_database');
    }

    public function resetDatabaseExecute(Request $request): JsonResponse
    {
        $this->requireSuperAdmin();

        $request->validate([
            'confirmation' => ['required', 'in:HAPUS SEMUA DATA'],
            'tenant_id' => 'nullable|exists:users,id',
        ]);

        $tenantId = $request->input('tenant_id');

        if ($tenantId) {
            // Reset data satu tenant
            $this->resetTenantData((int) $tenantId);
            $this->logActivity('reset_database', 'Tenant', $tenantId, "Tenant ID {$tenantId}", auth()->id());

            return response()->json(['status' => "Data tenant ID {$tenantId} berhasil dihapus."]);
        }

        // Reset semua data operasional (bukan users/subscription)
        DB::table('radacct')->delete();
        DB::table('radpostauth')->delete();
        PppUser::query()->delete();
        HotspotUser::query()->delete();
        Invoice::query()->delete();
        DB::table('transactions')->delete();

        $this->logActivity('reset_database', 'Database', 0, 'All operational data', auth()->id());

        return response()->json(['status' => 'Semua data operasional berhasil direset.']);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function requireSuperAdmin(): void
    {
        if (! auth()->user()->isSuperAdmin()) {
            abort(403);
        }
    }

    private function resetTenantData(int $tenantId): void
    {
        PppUser::where('owner_id', $tenantId)->delete();
        HotspotUser::where('owner_id', $tenantId)->delete();
        Invoice::where('owner_id', $tenantId)->delete();
        DB::table('transactions')->where('owner_id', $tenantId)->delete();
    }

    /** Normalize baris MixRadius → array siap insert (tidak create). */
    private function normalizePppRowMixRadius(array $data): array
    {
        $username = $data['Login'] ?? '';
        $name = $data['FullName'] ?? '';

        if (empty($username) || empty($name)) {
            throw new \InvalidArgumentException("Kolom 'Login' dan 'FullName' wajib diisi.");
        }

        $jatuhTempo = null;
        $expiredRaw = $data['Expired'] ?? '';
        if ($expiredRaw && ! str_starts_with($expiredRaw, '0000')) {
            try {
                $jatuhTempo = Carbon::parse($expiredRaw)->endOfDay()->toDateTimeString();
            } catch (\Throwable) {
            }
        }

        $statusAkun = ((string) ($data['AuthStatus'] ?? '1')) === '1' ? 'enable' : 'disable';
        $statusBayar = ((string) ($data['PaymentStatus'] ?? '')) === '1' ? 'sudah_bayar' : 'belum_bayar';
        $aksiJatuhTempo = strtolower($data['ExpiredAction'] ?? '') === 'isolir' ? 'isolir' : 'tetap_terhubung';

        $planName = $data['Plan'] ?? '';
        $ownerId = (int) $data['owner_id'];
        $profileId = null;
        if ($planName) {
            $profile = PppProfile::query()
                ->where('name', $planName)
                ->where(fn ($q) => $q->where('owner_id', $ownerId)->orWhereNull('owner_id'))
                ->first();
            $profileId = $profile?->id;
        }

        $nik = $data['IdCard'] ?? null;
        if ($nik === '0000000000000000' || $nik === '') {
            $nik = null;
        }

        $nomor = $data['Phone'] ?? null;
        if ($nomor) {
            $nomor = preg_replace('/\D+/', '', $nomor) ?? '';
            if (str_starts_with($nomor, '0')) {
                $nomor = '62'.substr($nomor, 1);
            } elseif (! str_starts_with($nomor, '62')) {
                $nomor = $nomor !== '' ? '62'.$nomor : null;
            }
            if ($nomor === '' || $nomor === '62') {
                $nomor = null;
            }
        }

        return [
            'owner_id' => $ownerId,
            'username' => $username,
            'ppp_password' => $data['Password'] ?? $username,
            'customer_name' => $name,
            'customer_id' => $data['CustomerId'] ?? null,
            'nik' => $nik,
            'nomor_hp' => $nomor,
            'email' => $data['Email'] ?? null,
            'alamat' => $data['Address'] ?? null,
            'latitude' => ($data['Latitude'] ?? '') !== '' ? $data['Latitude'] : null,
            'longitude' => ($data['Longitude'] ?? '') !== '' ? $data['Longitude'] : null,
            'ppp_profile_id' => $profileId,
            'status_akun' => $statusAkun,
            'status_bayar' => $statusBayar,
            'aksi_jatuh_tempo' => $aksiJatuhTempo,
            'tipe_service' => 'pppoe',
            'tipe_ip' => 'dhcp',
            'metode_login' => 'username_password',
            'jatuh_tempo' => $jatuhTempo,
            'catatan' => $data['Note'] ?? null,
        ];
    }

    /** Normalize baris format Rafen PPP → array siap insert. */
    private function normalizePppRow(array $data): array
    {
        foreach (['username', 'customer_name'] as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Kolom '{$field}' wajib diisi.");
            }
        }

        return [
            'owner_id' => (int) $data['owner_id'],
            'username' => $data['username'],
            'ppp_password' => $data['ppp_password'] ?? '',
            'customer_name' => $data['customer_name'],
            'customer_id' => $data['customer_id'] ?? null,
            'nik' => $data['nik'] ?? null,
            'nomor_hp' => $data['nomor_hp'] ?? null,
            'email' => $data['email'] ?? null,
            'alamat' => $data['alamat'] ?? null,
            'status_akun' => in_array($data['status_akun'] ?? '', ['enable', 'disable', 'isolir']) ? $data['status_akun'] : 'enable',
            'status_bayar' => in_array($data['status_bayar'] ?? '', ['sudah_bayar', 'belum_bayar']) ? $data['status_bayar'] : 'belum_bayar',
            'tipe_service' => $data['tipe_service'] ?? 'pppoe',
            'jatuh_tempo' => ! empty($data['jatuh_tempo']) ? Carbon::parse($data['jatuh_tempo'])->endOfDay()->toDateTimeString() : null,
            'catatan' => $data['catatan'] ?? null,
            'metode_login' => 'pppoe',
        ];
    }

    /** Normalize baris format Rafen Hotspot → array siap insert. */
    private function normalizeHotspotRow(array $data): array
    {
        foreach (['username', 'customer_name'] as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Kolom '{$field}' wajib diisi.");
            }
        }

        return [
            'owner_id' => (int) $data['owner_id'],
            'username' => $data['username'],
            'hotspot_password' => $data['hotspot_password'] ?? '',
            'customer_name' => $data['customer_name'],
            'customer_id' => $data['customer_id'] ?? null,
            'nik' => $data['nik'] ?? null,
            'nomor_hp' => $data['nomor_hp'] ?? null,
            'email' => $data['email'] ?? null,
            'alamat' => $data['alamat'] ?? null,
            'status_akun' => in_array($data['status_akun'] ?? '', ['enable', 'disable', 'isolir']) ? $data['status_akun'] : 'enable',
            'status_bayar' => in_array($data['status_bayar'] ?? '', ['sudah_bayar', 'belum_bayar']) ? $data['status_bayar'] : 'belum_bayar',
            'jatuh_tempo' => ! empty($data['jatuh_tempo']) ? Carbon::parse($data['jatuh_tempo'])->endOfDay()->toDateTimeString() : null,
            'catatan' => $data['catatan'] ?? null,
        ];
    }

    /** Bandingkan field yang relevan antara existing model dan normalized data. */
    private function diffUserData(PppUser|HotspotUser $existing, array $normalized, string $type): array
    {
        $fields = $type === 'ppp'
            ? ['customer_name', 'ppp_password', 'customer_id', 'nik', 'nomor_hp', 'email', 'alamat', 'status_akun', 'status_bayar', 'jatuh_tempo', 'catatan']
            : ['customer_name', 'hotspot_password', 'customer_id', 'nik', 'nomor_hp', 'email', 'alamat', 'status_akun', 'status_bayar', 'jatuh_tempo', 'catatan'];

        $diff = [];
        foreach ($fields as $field) {
            $existingVal = (string) ($existing->$field ?? '');
            // Normalize date for comparison
            if ($field === 'jatuh_tempo' && $existing->$field) {
                $existingVal = Carbon::parse($existing->$field)->format('Y-m-d');
            }
            $incomingVal = (string) ($normalized[$field] ?? '');
            if ($field === 'jatuh_tempo' && $incomingVal) {
                $incomingVal = Carbon::parse($incomingVal)->format('Y-m-d');
            }
            if ($existingVal !== $incomingVal) {
                $diff[$field] = ['existing' => $existingVal, 'incoming' => $incomingVal];
            }
        }

        return $diff;
    }

    private function summarizeUser(PppUser|HotspotUser $u, string $type): array
    {
        $pwField = $type === 'ppp' ? 'ppp_password' : 'hotspot_password';

        return [
            'customer_name' => $u->customer_name,
            'password' => $u->$pwField,
            'status_akun' => $u->status_akun,
            'status_bayar' => $u->status_bayar,
            'jatuh_tempo' => $u->jatuh_tempo ? Carbon::parse($u->jatuh_tempo)->format('Y-m-d') : '',
            'nomor_hp' => $u->nomor_hp,
            'email' => $u->email,
        ];
    }

    private function summarizeNormalized(array $n, string $type): array
    {
        $pwField = $type === 'ppp' ? 'ppp_password' : 'hotspot_password';

        return [
            'customer_name' => $n['customer_name'] ?? '',
            'password' => $n[$pwField] ?? '',
            'status_akun' => $n['status_akun'] ?? '',
            'status_bayar' => $n['status_bayar'] ?? '',
            'jatuh_tempo' => ! empty($n['jatuh_tempo']) ? Carbon::parse($n['jatuh_tempo'])->format('Y-m-d') : '',
            'nomor_hp' => $n['nomor_hp'] ?? '',
            'email' => $n['email'] ?? '',
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }

    private function formatDuration(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}
