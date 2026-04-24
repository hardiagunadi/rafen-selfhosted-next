<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $serviceNote->document_number }} - {{ $serviceNote->customer_name }}</title>
    @include('service-notes.partials.legacy-style')
    <style>
        body {
            background: #e2e8f0;
            color: #0f172a;
        }

        .page {
            max-width: 820px;
            margin: 0 auto;
            padding: 20px;
        }

        .toolbar,
        .print-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
        }

        .toolbar {
            margin-bottom: 18px;
            padding: 14px 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
        }

        .toolbar-status {
            font-size: 13px;
            color: #0f766e;
            font-weight: 700;
        }

        .toolbar-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .toolbar-button {
            border: 0;
            border-radius: 12px;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }

        .toolbar-button-primary {
            background: linear-gradient(135deg, #0f766e, #14b8a6);
            color: #fff;
        }

        .toolbar-button-secondary {
            background: #e2e8f0;
            color: #0f172a;
        }

        .print-card {
            padding: 14px;
        }

        @media print {
            body {
                background: #fff;
            }

            .page {
                max-width: none;
                padding: 0;
            }

            .toolbar {
                display: none !important;
            }

            .print-card {
                box-shadow: none;
                border-radius: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
@php
    $settings = $serviceNote->owner?->tenantSettings;
    $logo = $settings ? ($settings->invoice_logo ?: $settings->business_logo) : null;
    $companyName = $settings && $settings->business_name
        ? $settings->business_name
        : ($serviceNote->owner ? ($serviceNote->owner->company_name ?? $serviceNote->owner->name) : '');
    $companyAddress = $settings ? trim((string) ($settings->business_address ?? '')) : '';
    $companyPhone = $settings ? trim((string) ($settings->business_phone ?? '')) : '';
    $noteMetaRows = [
        ['label' => 'No. Nota', 'value' => $serviceNote->document_number],
        ['label' => 'No. Pelanggan', 'value' => $serviceNote->customer_id],
        ['label' => 'Metode Bayar', 'value' => strtoupper((string) ($serviceNote->payment_method ?? ''))],
    ];
    $noteCustomerRows = [
        ['label' => 'Nama', 'value' => $serviceNote->customer_name],
        ['label' => 'Alamat', 'value' => $serviceNote->customer_address],
        ['label' => 'No. HP', 'value' => $serviceNote->customer_phone],
    ];
    $noteServiceRows = [
        ['label' => 'Paket', 'value' => $serviceNote->package_name ?: '-'],
        ['label' => 'Username', 'value' => $serviceNote->pppUser?->username ?: '-'],
        ['label' => 'IP Static', 'value' => $serviceNote->pppUser?->ip_static],
        ['label' => 'ODP / POP', 'value' => $serviceNote->pppUser?->odp_pop],
    ];
    $transferAccounts = $serviceNote->payment_method === 'transfer'
        ? collect($serviceNote->transfer_accounts ?? [])
            ->filter(fn (array $account): bool => trim((string) ($account['account_number'] ?? '')) !== '')
            ->values()
            ->all()
        : [];
    $noteAmountRows = collect($serviceNote->item_lines ?? [])
        ->map(fn (array $item): array => [
            'label' => (string) ($item['label'] ?? '-'),
            'value' => number_format((float) ($item['amount'] ?? 0), 2, ',', '.'),
        ])
        ->values()
        ->all();
    $noteAmountRows[] = [
        'label' => $serviceNote->requiresTransferConfirmation() ? 'Total Tagihan' : 'Total Dibayar',
        'value' => number_format((float) $serviceNote->total, 2, ',', '.'),
        'row_class' => 'total-row',
    ];
@endphp

<div class="page">
    <div class="toolbar no-print">
        <div class="toolbar-status">
            @if ($serviceNote->requiresTransferConfirmation())
                Transfer untuk nota {{ $serviceNote->document_number }} masih menunggu konfirmasi penerimaan dana.
            @else
                Pendapatan tersimpan dengan nomor {{ $serviceNote->document_number }}.
            @endif
        </div>
        <div class="toolbar-actions">
            @if ($serviceNote->requiresTransferConfirmation())
                <a href="{{ route('service-notes.edit', $serviceNote) }}" class="toolbar-button toolbar-button-secondary">Edit Nota</a>
                <form method="POST" action="{{ route('service-notes.confirm-transfer', $serviceNote) }}">
                    @csrf
                    <button type="submit" class="toolbar-button toolbar-button-primary" onclick="return confirm('Tandai transfer untuk nota ini sudah diterima?')">Konfirmasi Transfer</button>
                </form>
            @endif
            <button type="button" class="toolbar-button toolbar-button-primary" onclick="window.print()">Cetak</button>
            <a href="{{ route('service-notes.index') }}" class="toolbar-button toolbar-button-secondary">Riwayat Nota</a>
            @if ($serviceNote->pppUser)
                <a href="{{ route('ppp-users.nota-layanan', ['pppUser' => $serviceNote->pppUser, 'type' => $serviceNote->note_type]) }}" class="toolbar-button toolbar-button-secondary">Buat Nota Baru</a>
            @endif
            <button type="button" class="toolbar-button toolbar-button-secondary" onclick="window.close()">Tutup</button>
        </div>
    </div>

    <div class="print-card">
        @include('service-notes.partials.legacy-document', [
            'noteLogo' => $logo ? Storage::url($logo) : null,
            'noteCompanyName' => $companyName,
            'noteCompanyAddress' => $companyAddress,
            'noteCompanyPhone' => $companyPhone,
            'noteDocumentTitle' => $serviceNote->document_title,
            'noteDateLabel' => 'Tanggal Nota',
            'noteDateText' => $serviceNote->note_date?->translatedFormat('d F Y') ?? '-',
            'noteMetaRows' => $noteMetaRows,
            'noteCustomerRows' => $noteCustomerRows,
            'noteServiceSectionTitle' => 'DATA LAYANAN',
            'noteServiceSectionVisible' => $serviceNote->show_service_section,
            'noteServiceRows' => $noteServiceRows,
            'noteSummaryTitle' => $serviceNote->summary_title,
            'noteAmountRows' => $noteAmountRows,
            'noteInWordsText' => ucfirst(terbilang((int) round((float) $serviceNote->total))).' rupiah',
            'noteNotesText' => trim((string) $serviceNote->notes),
            'noteFooterText' => $serviceNote->footer,
            'noteBottomText' => 'Dokumen dicetak dari nota elektronik - '.$companyName,
        ])

        @if ($serviceNote->payment_method === 'transfer' && $transferAccounts !== [])
            @include('service-notes.partials.transfer-document', [
                'transferLogo' => $logo ? Storage::url($logo) : null,
                'transferCompanyName' => $companyName,
                'transferCompanyAddress' => $companyAddress,
                'transferCompanyPhone' => $companyPhone,
                'transferDocumentNumber' => $serviceNote->document_number,
                'transferCustomerName' => $serviceNote->customer_name,
                'transferCustomerId' => $serviceNote->customer_id,
                'transferTotalText' => 'Rp '.number_format((float) $serviceNote->total, 0, ',', '.'),
                'transferAccounts' => $transferAccounts,
                'transferBottomText' => 'Lampiran transfer pembayaran - '.$companyName,
            ])
        @endif
    </div>
</div>

<script>
    window.addEventListener('load', function () {
        window.print();
    });
</script>
</body>
</html>
