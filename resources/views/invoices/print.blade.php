<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; background: #fff; color: #222; }

        .page { max-width: 800px; margin: 20px auto; padding: 30px; border: 1px solid #ddd; }

        /* Header */
        .invoice-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #333; }
        .business-info { flex: 1; }
        .business-logo { max-height: 64px; max-width: 160px; margin-bottom: 8px; display: block; }
        .business-name { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
        .business-detail { font-size: 11px; color: #555; line-height: 1.6; }

        .invoice-meta { text-align: right; min-width: 200px; }
        .invoice-title { font-size: 22px; font-weight: bold; letter-spacing: 2px; color: #333; margin-bottom: 10px; }
        .invoice-meta table { margin-left: auto; }
        .invoice-meta td { padding: 2px 4px; font-size: 11px; }
        .invoice-meta td:first-child { color: #777; text-align: right; }
        .invoice-meta td:last-child { font-weight: 600; text-align: left; padding-left: 8px; }

        /* Status badge */
        .status-paid { display: inline-block; background: #28a745; color: #fff; font-size: 10px; padding: 2px 8px; border-radius: 3px; font-weight: bold; letter-spacing: 1px; }
        .status-unpaid { display: inline-block; background: #ffc107; color: #333; font-size: 10px; padding: 2px 8px; border-radius: 3px; font-weight: bold; letter-spacing: 1px; }

        /* Bill to */
        .bill-to { margin-bottom: 20px; padding: 12px 14px; background: #f8f9fa; border-left: 3px solid #333; }
        .bill-to-label { font-size: 10px; text-transform: uppercase; color: #888; letter-spacing: 1px; margin-bottom: 4px; }
        .bill-to-name { font-size: 14px; font-weight: bold; }
        .bill-to-detail { font-size: 11px; color: #555; margin-top: 2px; }

        /* Items table */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .items-table thead tr { background: #333; color: #fff; }
        .items-table th { padding: 8px 10px; text-align: left; font-size: 11px; font-weight: 600; }
        .items-table th.text-right { text-align: right; }
        .items-table td { padding: 8px 10px; border-bottom: 1px solid #eee; font-size: 12px; vertical-align: top; }
        .items-table td.text-right { text-align: right; }
        .items-table tfoot td { border-top: 2px solid #333; font-weight: 700; font-size: 13px; }
        .items-table tfoot tr.subtotal td { border-top: 1px solid #ccc; font-weight: normal; font-size: 12px; }

        /* Payment info */
        .payment-section { margin-top: 20px; padding-top: 16px; border-top: 1px solid #ddd; }
        .payment-section h4 { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 10px; }
        .bank-list { display: flex; flex-wrap: wrap; gap: 10px; }
        .bank-item { background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; padding: 8px 12px; font-size: 11px; min-width: 180px; }
        .bank-item .bank-name { font-weight: bold; font-size: 12px; margin-bottom: 2px; }
        .bank-item .bank-number { font-family: 'Courier New', monospace; font-size: 13px; color: #222; }
        .bank-item .bank-holder { color: #555; }

        /* Notes */
        .notes-section { margin-top: 16px; padding: 10px 14px; background: #fffbe6; border: 1px solid #ffe58f; border-radius: 3px; font-size: 11px; color: #555; }
        .footer-text { margin-top: 14px; font-size: 10px; color: #aaa; text-align: center; border-top: 1px solid #eee; padding-top: 10px; }

        /* Print controls */
        .print-controls { text-align: center; margin-bottom: 20px; }
        .btn-print { background: #333; color: #fff; border: none; padding: 8px 24px; font-size: 13px; cursor: pointer; border-radius: 3px; margin-right: 8px; }
        .btn-close { background: #fff; color: #333; border: 1px solid #ccc; padding: 8px 24px; font-size: 13px; cursor: pointer; border-radius: 3px; }

        @media print {
            .print-controls { display: none; }
            .page { border: none; margin: 0; padding: 20px; max-width: 100%; }
            body { font-size: 11px; }
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    </style>
</head>
<body>

<div class="print-controls no-print">
    <button class="btn-print" onclick="window.print()">&#128424; Cetak Invoice</button>
    <button class="btn-close" onclick="window.close()">&#10005; Tutup</button>
</div>

<div class="page">

    {{-- Header: Info Bisnis + Judul Invoice --}}
    <div class="invoice-header">
        <div class="business-info">
            @if($settings?->invoice_logo || $settings?->business_logo)
                <img src="{{ Storage::url($settings->invoice_logo ?: $settings->business_logo) }}" alt="Logo" class="business-logo">
            @endif
            <div class="business-name">{{ $settings?->business_name ?? $invoice->owner?->company_name ?? $invoice->owner?->name }}</div>
            <div class="business-detail">
                @if($settings?->business_address)
                    {{ $settings->business_address }}<br>
                @endif
                @if($settings?->business_phone)
                    Telp: {{ $settings->business_phone }}
                    @if($settings?->business_email) &nbsp;|&nbsp; Email: {{ $settings->business_email }} @endif
                    <br>
                @elseif($settings?->business_email)
                    Email: {{ $settings->business_email }}<br>
                @endif
                @if($settings?->website)
                    Web: {{ $settings->website }}<br>
                @endif
                @if($settings?->npwp)
                    NPWP: {{ $settings->npwp }}
                @endif
            </div>
        </div>

        <div class="invoice-meta">
            <div class="invoice-title">INVOICE</div>
            <table>
                <tr>
                    <td>No.</td>
                    <td>{{ $invoice->invoice_number }}</td>
                </tr>
                <tr>
                    <td>Tanggal</td>
                    <td>{{ $invoice->created_at->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td>Jatuh Tempo</td>
                    <td>{{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-' }}</td>
                </tr>
                <tr>
                    <td>Status</td>
                    <td>
                        @if($invoice->isPaid())
                            <span class="status-paid">LUNAS</span>
                        @else
                            <span class="status-unpaid">BELUM BAYAR</span>
                        @endif
                    </td>
                </tr>
                @if($invoice->isPaid() && $invoice->paid_at)
                <tr>
                    <td>Dibayar</td>
                    <td>{{ $invoice->paid_at->format('d/m/Y') }}</td>
                </tr>
                @endif
            </table>
        </div>
    </div>

    {{-- Kepada --}}
    <div class="bill-to">
        <div class="bill-to-label">Kepada</div>
        <div class="bill-to-name">{{ $invoice->customer_name }}</div>
        <div class="bill-to-detail">
            @if($invoice->customer_id) ID Pelanggan: {{ $invoice->customer_id }} &nbsp;|&nbsp; @endif
            Tipe: {{ strtoupper($invoice->tipe_service ?? '-') }}
        </div>
    </div>

    {{-- Tabel Item --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 60%">Deskripsi</th>
                <th class="text-right">Harga</th>
                <th class="text-right">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    Layanan Internet — {{ $invoice->paket_langganan ?? '-' }}
                    @if($invoice->promo_applied)
                        <br><small style="color:#888">(Harga Promo)</small>
                    @endif
                </td>
                <td class="text-right">Rp {{ number_format($invoice->harga_dasar, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($invoice->harga_dasar, 0, ',', '.') }}</td>
            </tr>
        </tbody>
        <tfoot>
            @if($invoice->ppn_percent > 0)
            <tr class="subtotal">
                <td colspan="2" class="text-right" style="border-top:1px solid #ccc; font-weight:normal; font-size:11px; color:#555;">
                    PPN ({{ number_format($invoice->ppn_percent, 0) }}%)
                </td>
                <td class="text-right" style="border-top:1px solid #ccc; font-size:11px;">
                    Rp {{ number_format($invoice->ppn_amount, 0, ',', '.') }}
                </td>
            </tr>
            @endif
            <tr>
                <td colspan="2" class="text-right">TOTAL</td>
                <td class="text-right">Rp {{ number_format($invoice->total, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- Info Pembayaran --}}
    @if($invoice->isPaid())
        <div style="margin-top:12px; padding:10px 14px; background:#d4edda; border:1px solid #c3e6cb; border-radius:3px; font-size:12px;">
            <strong>&#10003; Pembayaran diterima</strong>
            @if($invoice->payment_channel ?? $invoice->payment_method)
                &nbsp;&mdash;&nbsp; via {{ $invoice->payment_channel ?? $invoice->payment_method }}
            @endif
            @if($invoice->payment_reference)
                &nbsp;| Ref: {{ $invoice->payment_reference }}
            @endif
        </div>
    @elseif($bankAccounts->isNotEmpty())
        <div class="payment-section">
            <h4>Pembayaran ke</h4>
            <div class="bank-list">
                @foreach($bankAccounts as $bank)
                <div class="bank-item">
                    <div class="bank-name">{{ $bank->bank_name }}@if($bank->branch) — {{ $bank->branch }}@endif</div>
                    <div class="bank-number">{{ $bank->account_number }}</div>
                    <div class="bank-holder">a/n {{ $bank->account_name }}</div>
                </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Catatan --}}
    @if($settings?->invoice_notes)
    <div class="notes-section">
        <strong>Catatan:</strong> {{ $settings->invoice_notes }}
    </div>
    @endif

    {{-- Footer --}}
    <div class="footer-text">
        @if($settings?->invoice_footer)
            {{ $settings->invoice_footer }}
        @else
            Terima kasih atas kepercayaan Anda menggunakan layanan kami.
        @endif
    </div>

</div>

</body>
</html>
