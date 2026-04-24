<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $serviceNote->document_number }} - {{ $serviceNote->customer_name }}</title>
    <style>
        :root {
            --text: #0f172a;
            --muted: #64748b;
            --line: #cbd5e1;
            --bg: #e2e8f0;
            --surface: #ffffff;
            --accent: #0f766e;
        }

        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: var(--text); background: var(--bg); }
        .page { max-width: 920px; margin: 0 auto; padding: 24px; }
        .toolbar, .sheet { background: var(--surface); border-radius: 18px; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12); }
        .toolbar { padding: 14px 16px; margin-bottom: 18px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: center; }
        .toolbar strong { color: var(--accent); }
        .toolbar-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .toolbar-button { border: 0; border-radius: 12px; padding: 10px 16px; font-size: 14px; font-weight: 700; cursor: pointer; text-decoration: none; }
        .toolbar-button-primary { background: linear-gradient(135deg, #0f766e, #14b8a6); color: #fff; }
        .toolbar-button-secondary { background: #e2e8f0; color: var(--text); }
        .sheet { padding: 24px; }
        .sheet + .sheet { margin-top: 18px; }
        .header { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; padding-bottom: 18px; margin-bottom: 18px; border-bottom: 1px solid var(--line); }
        .brand { display: flex; gap: 14px; align-items: flex-start; }
        .brand-logo { width: 72px; height: 72px; object-fit: contain; }
        .brand-title { font-size: 24px; font-weight: 700; margin: 0 0 6px; }
        .brand-meta { color: var(--muted); font-size: 13px; line-height: 1.5; margin: 0; }
        .doc-title { text-align: right; }
        .doc-title h1 { margin: 0 0 8px; font-size: 24px; line-height: 1.2; }
        .doc-title p { margin: 0; color: var(--muted); font-size: 14px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; margin-bottom: 18px; }
        .panel { border: 1px solid var(--line); border-radius: 14px; padding: 16px; }
        .panel h2 { margin: 0 0 12px; font-size: 15px; }
        .meta-list { display: grid; gap: 8px; font-size: 14px; }
        .meta-row { display: grid; grid-template-columns: 140px 1fr; gap: 10px; }
        .meta-row strong { color: var(--muted); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; border-bottom: 1px solid var(--line); font-size: 14px; vertical-align: top; }
        th { text-align: left; background: #f8fafc; }
        .text-right { text-align: right; }
        .total-row td { font-weight: 700; font-size: 15px; }
        .notes-box { margin-top: 18px; border: 1px dashed var(--line); border-radius: 14px; padding: 14px 16px; font-size: 14px; }
        .notes-box h3 { margin: 0 0 8px; font-size: 14px; }
        .footer-text { margin-top: 18px; color: var(--muted); font-size: 13px; text-align: center; }
        .signature { margin-top: 28px; display: flex; justify-content: flex-end; }
        .signature-box { width: 220px; text-align: center; font-size: 14px; }
        .signature-space { height: 72px; }

        @media (max-width: 768px) {
            .page { padding: 16px; }
            .grid { grid-template-columns: 1fr; }
            .header { flex-direction: column; }
            .doc-title { text-align: left; }
            .meta-row { grid-template-columns: 1fr; gap: 4px; }
        }

        @media print {
            body { background: #fff; }
            .page { max-width: none; padding: 0; }
            .toolbar { display: none !important; }
            .sheet { box-shadow: none; border-radius: 0; padding: 0; }
            .sheet + .sheet { margin-top: 16px; page-break-before: always; }
        }
    </style>
</head>
<body>
@php
    $settings = $serviceNote->owner?->tenantSettings;
    $logo = $settings ? ($settings->invoice_logo ?: $settings->business_logo) : null;
    $logoUrl = $logo ? asset('storage/' . $logo) : null;
    $companyName = $settings && $settings->business_name ? $settings->business_name : ($serviceNote->owner ? ($serviceNote->owner->company_name ?? $serviceNote->owner->name) : '');
    $companyAddress = trim((string) ($settings?->business_address ?? ''));
    $companyPhone = trim((string) ($settings?->business_phone ?? ''));
    $transferAccounts = collect($serviceNote->transfer_accounts ?? [])->filter(fn (array $account): bool => trim((string) ($account['account_number'] ?? '')) !== '')->values();
@endphp

<div class="page">
    <div class="toolbar">
        <div>Pendapatan tersimpan dengan nomor <strong>{{ $serviceNote->document_number }}</strong></div>
        <div class="toolbar-actions">
            <button type="button" class="toolbar-button toolbar-button-primary" onclick="window.print()">Cetak</button>
            <a href="{{ route('service-notes.index') }}" class="toolbar-button toolbar-button-secondary">Riwayat Nota</a>
            @if ($serviceNote->pppUser)
                <a href="{{ route('ppp-users.nota-layanan', ['pppUser' => $serviceNote->pppUser, 'type' => $serviceNote->note_type]) }}" class="toolbar-button toolbar-button-secondary">Buat Nota Baru</a>
            @endif
            <button type="button" class="toolbar-button toolbar-button-secondary" onclick="window.close()">Tutup</button>
        </div>
    </div>

    <div class="sheet">
        <div class="header">
            <div class="brand">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="Logo {{ $companyName }}" class="brand-logo">
                @endif
                <div>
                    <p class="brand-title">{{ $companyName ?: 'RAFEN Manager' }}</p>
                    <p class="brand-meta">{{ $companyAddress !== '' ? $companyAddress : '-' }}<br>{{ $companyPhone !== '' ? $companyPhone : '-' }}</p>
                </div>
            </div>
            <div class="doc-title">
                <h1>{{ $serviceNote->document_title }}</h1>
                <p>Tanggal Nota: {{ $serviceNote->note_date?->translatedFormat('d F Y') ?? '-' }}</p>
            </div>
        </div>

        <div class="grid">
            <div class="panel">
                <h2>Informasi Nota</h2>
                <div class="meta-list">
                    <div class="meta-row"><strong>No. Nota</strong><span>{{ $serviceNote->document_number }}</span></div>
                    <div class="meta-row"><strong>Metode Bayar</strong><span>{{ strtoupper((string) ($serviceNote->payment_method ?? '-')) }}</span></div>
                    <div class="meta-row"><strong>Jenis Nota</strong><span>{{ strtoupper((string) $serviceNote->note_type) }}</span></div>
                    <div class="meta-row"><strong>Petugas</strong><span>{{ $serviceNote->paidBy?->name ?? '-' }}</span></div>
                </div>
            </div>
            <div class="panel">
                <h2>Data Pelanggan</h2>
                <div class="meta-list">
                    <div class="meta-row"><strong>No. Pelanggan</strong><span>{{ $serviceNote->customer_id ?: '-' }}</span></div>
                    <div class="meta-row"><strong>Nama</strong><span>{{ $serviceNote->customer_name ?: '-' }}</span></div>
                    <div class="meta-row"><strong>No. HP</strong><span>{{ $serviceNote->customer_phone ?: '-' }}</span></div>
                    <div class="meta-row"><strong>Alamat</strong><span>{{ $serviceNote->customer_address ?: '-' }}</span></div>
                </div>
            </div>
        </div>

        @if ($serviceNote->show_service_section)
            <div class="panel" style="margin-bottom:18px;">
                <h2>Data Layanan</h2>
                <div class="meta-list">
                    <div class="meta-row"><strong>Paket</strong><span>{{ $serviceNote->package_name ?: '-' }}</span></div>
                    <div class="meta-row"><strong>Username</strong><span>{{ $serviceNote->pppUser?->username ?: '-' }}</span></div>
                    <div class="meta-row"><strong>IP Static</strong><span>{{ $serviceNote->pppUser?->ip_static ?: '-' }}</span></div>
                    <div class="meta-row"><strong>ODP / POP</strong><span>{{ $serviceNote->pppUser?->odp_pop ?: '-' }}</span></div>
                </div>
            </div>
        @endif

        <table>
            <thead>
                <tr>
                    <th>{{ $serviceNote->summary_title }}</th>
                    <th class="text-right">Nominal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($serviceNote->item_lines ?? [] as $item)
                    <tr>
                        <td>{{ $item['label'] ?? '-' }}</td>
                        <td class="text-right">{{ number_format((float) ($item['amount'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td>Total Dibayar</td>
                    <td class="text-right">{{ number_format((float) $serviceNote->total, 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>

        <div class="notes-box">
            <h3>Terbilang</h3>
            <div>{{ ucfirst(terbilang((int) round((float) $serviceNote->total))) }} rupiah</div>
        </div>

        @if (filled($serviceNote->notes))
            <div class="notes-box">
                <h3>Catatan</h3>
                <div>{{ $serviceNote->notes }}</div>
            </div>
        @endif

        @if (filled($serviceNote->footer))
            <div class="notes-box">
                <h3>Footer</h3>
                <div>{{ $serviceNote->footer }}</div>
            </div>
        @endif

        <div class="signature">
            <div class="signature-box">
                <div>Petugas</div>
                <div class="signature-space"></div>
                <strong>{{ $serviceNote->paidBy?->name ?? $serviceNote->creator?->name ?? '-' }}</strong>
            </div>
        </div>

        <div class="footer-text">Dokumen dicetak dari nota elektronik - {{ $companyName ?: 'RAFEN Manager' }}</div>
    </div>

    @if ($serviceNote->payment_method === 'transfer' && $transferAccounts->isNotEmpty())
        <div class="sheet">
            <div class="header">
                <div>
                    <p class="brand-title" style="font-size:20px;margin-bottom:4px;">DAFTAR REKENING PEMBAYARAN TRANSFER</p>
                    <p class="brand-meta">Lampiran pembayaran untuk nota {{ $serviceNote->document_number }}</p>
                </div>
                <div class="doc-title">
                    <h1 style="font-size:20px;">{{ $serviceNote->customer_name ?: '-' }}</h1>
                    <p>{{ $serviceNote->customer_id ?: '-' }}</p>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Bank</th>
                        <th>No. Rekening</th>
                        <th>Atas Nama</th>
                        <th>Cabang</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transferAccounts as $account)
                        <tr>
                            <td>{{ $account['bank_name'] ?? '-' }}</td>
                            <td>{{ $account['account_number'] ?? '-' }}</td>
                            <td>{{ $account['account_name'] ?? '-' }}</td>
                            <td>{{ $account['branch'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="notes-box">
                <h3>Total Pembayaran</h3>
                <div>Rp {{ number_format((float) $serviceNote->total, 0, ',', '.') }}</div>
            </div>

            <div class="footer-text">Lampiran transfer pembayaran - {{ $companyName ?: 'RAFEN Manager' }}</div>
        </div>
    @endif
</div>

<script>
    window.addEventListener('load', function () {
        window.print();
    });
</script>
</body>
</html>
