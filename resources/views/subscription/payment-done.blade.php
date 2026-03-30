<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Langganan — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background: #f4f6f9; }
        .portal-card { max-width: 480px; margin: 3rem auto; }
    </style>
</head>
<body>
<div class="portal-card">
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            @if($subscription->status === 'active')
                <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                <h4 class="font-weight-bold">Langganan Aktif!</h4>
                <p class="text-muted">Pembayaran Anda telah dikonfirmasi.<br>Terima kasih!</p>
            @elseif($subscription->status === 'cancelled')
                <i class="fas fa-times-circle text-danger fa-4x mb-3"></i>
                <h4 class="font-weight-bold">Langganan Dibatalkan</h4>
                <p class="text-muted">Silakan hubungi administrator jika ada pertanyaan.</p>
            @else
                <i class="fas fa-info-circle text-info fa-4x mb-3"></i>
                <h4 class="font-weight-bold">Tagihan Sudah Diproses</h4>
                <p class="text-muted">Status langganan: <strong>{{ $subscription->status }}</strong></p>
            @endif
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
