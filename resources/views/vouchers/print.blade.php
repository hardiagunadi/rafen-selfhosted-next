<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Voucher — {{ $batch }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; background: #fff; }

        .page-header {
            text-align: center;
            padding: 10px 0 6px;
            border-bottom: 2px solid #333;
            margin-bottom: 10px;
        }
        .page-header h2 { font-size: 14px; }
        .page-header p { font-size: 10px; color: #555; }

        .voucher-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 0 6px;
        }

        .voucher-card {
            width: calc(33.333% - 4px);
            border: 1px dashed #888;
            border-radius: 4px;
            padding: 8px 10px;
            text-align: center;
        }

        .voucher-card .profile-name {
            font-size: 9px;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 2px;
        }

        .voucher-card .voucher-code {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 2px;
            font-family: 'Courier New', monospace;
            color: #111;
        }

        .voucher-card .batch {
            font-size: 8px;
            color: #999;
            margin-top: 2px;
        }

        .voucher-card .expired-info {
            font-size: 8px;
            color: #c00;
            margin-top: 2px;
        }

        .no-print { text-align: center; padding: 10px; }

        @media print {
            .no-print { display: none; }
            body { -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="page-header no-print">
        <h2>Voucher Batch: {{ $batch }}</h2>
        <p>Total: {{ $vouchers->count() }} voucher — Dicetak: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="no-print" style="margin-bottom: 10px;">
        <button onclick="window.print()" style="padding:6px 20px;font-size:13px;cursor:pointer;">🖨️ Print</button>
        <button onclick="window.close()" style="padding:6px 20px;font-size:13px;cursor:pointer;margin-left:6px;">✕ Tutup</button>
    </div>

    <div class="voucher-grid">
        @foreach($vouchers as $voucher)
            <div class="voucher-card">
                <div class="profile-name">{{ $voucher->hotspotProfile?->name ?? $batch }}</div>
                <div class="voucher-code">{{ $voucher->code }}</div>
                <div class="batch">{{ $batch }}</div>
                @if($voucher->expired_at)
                    <div class="expired-info">Exp: {{ $voucher->expired_at->format('d/m/Y') }}</div>
                @endif
            </div>
        @endforeach
    </div>
</body>
</html>
