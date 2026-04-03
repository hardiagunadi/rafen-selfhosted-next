<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Cetak Ulang - {{ $invoice->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 28px 32px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .warning-header {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 18px;
        }
        .warning-icon {
            font-size: 22px;
            flex-shrink: 0;
        }
        .warning-title {
            font-size: 15px;
            font-weight: bold;
            color: #856404;
        }
        .info-row {
            margin-bottom: 8px;
            font-size: 13px;
            color: #333;
        }
        .info-row span {
            font-weight: bold;
        }
        .divider {
            border: none;
            border-top: 1px solid #eee;
            margin: 16px 0;
        }
        .question {
            font-size: 13px;
            color: #555;
            margin-bottom: 18px;
        }
        .btn-group {
            display: flex;
            gap: 10px;
        }
        .btn-confirm {
            flex: 1;
            padding: 9px 14px;
            background: #e65100;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }
        .btn-confirm:hover { background: #bf360c; color: #fff; }
        .btn-cancel {
            flex: 1;
            padding: 9px 14px;
            background: #f5f5f5;
            color: #333;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 13px;
            cursor: pointer;
        }
        .btn-cancel:hover { background: #e0e0e0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="warning-header">
            <div class="warning-icon">&#9888;</div>
            <div class="warning-title">Nota Sudah Pernah Dicetak</div>
        </div>

        <div class="info-row">No. Invoice: <span>{{ $invoice->invoice_number }}</span></div>
        <div class="info-row">Pelanggan: <span>{{ $invoice->customer_name }}</span></div>

        <hr class="divider">

        <div class="info-row">Dicetak pertama oleh: <span>{{ $invoice->notaPrintedBy?->name ?? '-' }}</span></div>
        <div class="info-row">Pada: <span>{{ $invoice->nota_printed_at->translatedFormat('d F Y, H:i') }}</span></div>

        <hr class="divider">

        <p class="question">Apakah Anda yakin ingin mencetak ulang nota ini?</p>

        <div class="btn-group">
            <a href="{{ route('invoices.nota', $invoice->id) }}?confirm=1" target="_blank" class="btn-confirm">
                Cetak Ulang
            </a>
            <button onclick="window.close()" class="btn-cancel">Batal / Tutup</button>
        </div>
    </div>
</body>
</html>
