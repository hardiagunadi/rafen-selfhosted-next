<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Pelanggan</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg,#0a3e68 0%,#0f6b95 55%,#0c8a8f 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .box { background:#fff; border-radius:16px; box-shadow:0 8px 40px rgba(10,62,104,.3); padding:2rem; max-width:420px; width:100%; }
        .box h5 { color:#0a3e68; font-weight:700; margin-bottom:1.25rem; }
        .isp-item { display:block; padding:.85rem 1rem; border:1px solid #d0dbe8; border-radius:10px; margin-bottom:.65rem; color:#0a3e68; text-decoration:none; font-weight:600; transition:background .15s; }
        .isp-item:hover { background:#f0f6fb; color:#0f6b95; text-decoration:none; }
        .isp-item i { margin-right:.5rem; color:#0f6b95; }
    </style>
</head>
<body>
<div class="box">
    <div class="text-center mb-3">
        <i class="fas fa-wifi fa-2x" style="color:#0f6b95;"></i>
    </div>
    <h5 class="text-center">Portal Pelanggan</h5>
    @if($tenants->isEmpty())
        <p class="text-muted text-center">Tidak ada portal yang tersedia.</p>
    @else
        <p class="text-muted text-center small mb-3">Pilih penyedia layanan internet Anda:</p>
        @foreach($tenants as $t)
        <a href="{{ $t->portalLoginUrl() }}" class="isp-item">
            <i class="fas fa-network-wired"></i> {{ $t->business_name ?? $t->portal_slug }}
        </a>
        @endforeach
    @endif
</div>
</body>
</html>
