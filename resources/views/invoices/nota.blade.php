<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Nota - {{ $invoice->invoice_number }}</title>
@verbatim
    <style>
        @page {
            size: 140mm 120mm;
            margin: 5mm 5mm;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            color: #000;
        }

        .wrapper {
            width: 100%;
            box-sizing: border-box;
        }

        .header {
            display: flex;
            align-items: center;
            margin-bottom: 6px;
            margin-top: 7px;
        }

        .logo {
            width: 65px;
        }

        .header-text {
            flex: 1;
            text-align: center;
        }

        .header-text .title {
            font-weight: bold;
            font-size: 17px;
        }

        .header-text .subtitle {
            font-size: 13px;
        }

        .top-info {
            margin-top: 6px;
            font-size: 13px;
        }

        .top-info table {
            width: 100%;
        }

        .top-info td {
            vertical-align: top;
        }

        .bold { font-weight: bold; }
        .text-right { text-align: right; }
        .mt-3 { margin-top: 6px; }

        .amount-table {
            width: 100%;
            margin-top: 6px;
            font-size: 13px;
        }

        .amount-table td {
            padding: 1px 0;
        }

        .amount-table .label {
            width: 60%;
        }

        .amount-table .value {
            width: 40%;
        }

        .total-row {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            font-weight: bold;
        }

        .nominal-underline {
            display: inline-block;
            min-width: 60px;
            border-bottom: 1px solid #000;
            padding-bottom: 1px;
        }

        .no-print { margin-bottom: 10px; }

        @media print {
            .no-print { display: none; }
        }

        .reprint-banner {
            text-align: center;
            font-weight: bold;
            font-size: 11px;
            color: #cc0000;
            border: 1px solid #cc0000;
            padding: 2px 4px;
            margin-bottom: 4px;
            letter-spacing: 1px;
        }
    </style>
@endverbatim
</head>
<body>

@php
    $bulanNames   = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $refDate      = $invoice->due_date ?? $invoice->created_at;
    $bulanIdx     = ($refDate->month - 1) < 1 ? 12 : ($refDate->month - 1);
    $tagihanBulan = $bulanNames[$bulanIdx];
    $terbilang    = ucfirst(terbilang((int) $invoice->total));
    $logo         = $settings ? ($settings->invoice_logo ?: $settings->business_logo) : null;
    $coName       = $settings && $settings->business_name
                        ? $settings->business_name
                        : ($invoice->owner ? ($invoice->owner->company_name ?? $invoice->owner->name) : '');
    $coAddr       = $settings ? ($settings->business_address ?? '') : '';
    $coPhone      = $settings ? ($settings->business_phone ?? '') : '';
    $dueDate      = $invoice->due_date ? $invoice->due_date->translatedFormat('d F Y') : '-';
    $custAlamat   = $invoice->pppUser ? ($invoice->pppUser->alamat ?? '') : '';
    $paketLine    = $invoice->paket_langganan ?? '-';
    $footer       = $settings ? ($settings->invoice_footer ?? '') : '';
@endphp

<div class="no-print">
    @if($isReprint ?? false)
        <div style="background:#fff3cd;border:1px solid #ffc107;padding:6px 10px;margin-bottom:6px;font-size:12px;">
            &#9888; Nota ini sudah pernah dicetak sebelumnya (Cetak Ulang)
        </div>
    @endif
    <button onclick="window.print()">Print</button>
    <button onclick="window.close()">Tutup</button>
</div>

<div class="wrapper">
    @if($isReprint ?? false)
        <div class="reprint-banner">*** CETAK ULANG ***</div>
    @endif

    {{-- HEADER --}}
    <div class="header">
        @if($logo)
            <img src="{{ Storage::url($logo) }}" class="logo" alt="Logo">
        @endif
        <div class="header-text">
            <div class="title">{{ $coName }}</div>
            @if($coAddr || $coPhone)
                <div class="subtitle">
                    @if($coAddr){{ $coAddr }}<br>@endif
                    @if($coPhone)HP. {{ $coPhone }}@endif
                </div>
            @endif
        </div>
    </div>

    {{-- TOP INFO --}}
    <div class="top-info">
        <table>
            <tr>
                <td style="width:50%;">
                    <div>Tgl. Jatuh Tempo</div>
                    <div class="bold">{{ $dueDate }}</div>
                </td>
                <td style="width:50%;">
                    <table style="width:100%;">
                        <tr>
                            <td>No. Invoice</td>
                            <td>: {{ $invoice->invoice_number }}</td>
                        </tr>
                        <tr>
                            <td>Tagihan Bulan</td>
                            <td>: {{ $tagihanBulan }}</td>
                        </tr>
                        @if($invoice->customer_id)
                        <tr>
                            <td>Nomor Pelanggan</td>
                            <td>: {{ $invoice->customer_id }}</td>
                        </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <br><br>

    {{-- PELANGGAN --}}
    <div class="mt-3">
        <div class="bold">{{ $invoice->customer_name }}</div>
        @if($custAlamat)
            <div>{{ $custAlamat }}</div>
        @endif
    </div>

    <br><br>

    {{-- TABEL BIAYA --}}
    <table class="amount-table mt-3">
        <tr>
            <td class="label bold">Biaya Langganan</td>
            <td class="value text-right">{{ number_format($invoice->harga_dasar, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label"><em>{{ $paketLine }}</em></td>
            <td></td>
        </tr>
        @if($invoice->ppn_percent > 0)
        <tr>
            <td class="label">PPN ({{ number_format($invoice->ppn_percent, 0) }}%)</td>
            <td class="value text-right">
                <span class="nominal-underline">{{ number_format($invoice->ppn_amount, 2, ',', '.') }}</span>
            </td>
        </tr>
        @endif
        <tr>
            <td class="label">Biaya Admin Bank/Loket</td>
            <td class="value text-right">
                <span class="nominal-underline">0</span>
            </td>
        </tr>
        <tr class="total-row">
            <td class="label">Total Dibayar</td>
            <td class="value text-right">{{ number_format($invoice->total, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="mt-3 bold"><em>{{ $terbilang }} rupiah</em></div>

    {{-- INFO PENERIMA BAYAR --}}
    @if($invoice->paid_by && $invoice->paidBy)
    <div class="mt-3" style="font-size:11px;">
        Diterima oleh: <strong>{{ $invoice->paidBy->name }}</strong>
        @if($invoice->paid_at)
            &mdash; {{ $invoice->paid_at->translatedFormat('d F Y H:i') }}
        @endif
        @if($invoice->cash_received)
            <br>Tunai: Rp {{ number_format($invoice->cash_received, 0, ',', '.') }}
        @endif
        @if($invoice->transfer_amount)
            &nbsp;&nbsp;Transfer: Rp {{ number_format($invoice->transfer_amount, 0, ',', '.') }}
        @endif
        @if($invoice->payment_note)
            <br>Catatan: {{ $invoice->payment_note }}
        @endif
    </div>
    @endif

    {{-- FOOTER --}}
    @if($footer)
        <div class="mt-3" style="font-size:11px; text-align:center;">{{ $footer }}</div>
    @endif

</div>

</body>
</html>
