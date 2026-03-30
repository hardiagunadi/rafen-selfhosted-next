<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tagihan {{ $invoice->invoice_number }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background: #f4f6f9; }
        .portal-card { max-width: 640px; margin: 2rem auto; }
        .badge-paid { font-size: 1.1rem; padding: .5rem 1.2rem; }
        .proof-preview { max-width: 100%; max-height: 300px; border-radius: .5rem; border: 1px solid #dee2e6; cursor: zoom-in; }
        .section-divider { border-top: 2px dashed #dee2e6; margin: 1.5rem 0; }
        .brand-header { background: #343a40; color: #fff; border-radius: .5rem .5rem 0 0; padding: 1.25rem 1.5rem; }
        .brand-header .business-name { font-size: 1.2rem; font-weight: 700; margin: 0; }
        .brand-header .business-sub  { font-size: .85rem; opacity: .75; margin: 0; }
        .payment-tab .nav-link { border-radius: .375rem .375rem 0 0; font-weight: 500; }
        .payment-tab .nav-link.active { background: #007bff; color: #fff; border-color: #007bff; }
    </style>
</head>
<body>
@php
    $serverLoadTimeMs = defined('LARAVEL_START')
        ? (microtime(true) - LARAVEL_START) * 1000
        : null;
@endphp

<div class="portal-card">

    {{-- Brand Header --}}
    <div class="brand-header d-flex align-items-center">
        @if($settings && ($settings->invoice_logo || $settings->business_logo))
            <img src="{{ asset('storage/' . ($settings->invoice_logo ?: $settings->business_logo)) }}" alt="Logo" style="height:40px; margin-right:12px; border-radius:4px;">
        @else
            <i class="fas fa-wifi fa-2x mr-3 text-info"></i>
        @endif
        <div>
            <p class="business-name">{{ $settings->business_name ?? 'Portal Pembayaran' }}</p>
            @if($settings && $settings->business_phone)
                <p class="business-sub"><i class="fas fa-phone-alt mr-1"></i>{{ $settings->business_phone }}</p>
            @endif
        </div>
    </div>

    <div class="card shadow-sm" style="border-radius: 0 0 .5rem .5rem; border-top: none;">
        <div class="card-body">

            {{-- Flash messages --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            {{-- Invoice Info --}}
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h5 class="font-weight-bold mb-1">{{ $invoice->invoice_number }}</h5>
                    <span class="text-muted small">{{ $invoice->customer_name }}</span>
                    @if($invoice->customer_id)
                        <span class="text-muted small ml-2">· ID: {{ $invoice->customer_id }}</span>
                    @endif
                </div>
                @if($invoice->isPaid())
                    <span class="badge badge-success badge-paid"><i class="fas fa-check-circle mr-1"></i>LUNAS</span>
                @else
                    <span class="badge badge-warning badge-paid" style="color:#212529"><i class="fas fa-clock mr-1"></i>BELUM BAYAR</span>
                @endif
            </div>

            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-muted" style="width:40%">Paket</td>
                    <td class="font-weight-bold">{{ $invoice->paket_langganan ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Total Tagihan</td>
                    <td class="font-weight-bold text-success" style="font-size:1.1rem">Rp {{ number_format($invoice->total, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Jatuh Tempo</td>
                    <td @if($invoice->isOverdue()) class="text-danger font-weight-bold" @endif>
                        {{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-' }}
                        @if($invoice->isOverdue()) <span class="badge badge-danger ml-1">Overdue</span> @endif
                    </td>
                </tr>
                @if($invoice->isPaid())
                    <tr>
                        <td class="text-muted">Dibayar pada</td>
                        <td>{{ $invoice->paid_at ? $invoice->paid_at->format('d/m/Y H:i') : '-' }}</td>
                    </tr>
                    @if($invoice->payment_method)
                    <tr>
                        <td class="text-muted">Metode</td>
                        <td>{{ $invoice->payment_channel ?? $invoice->payment_method }}</td>
                    </tr>
                    @endif
                @endif
            </table>

            @if($invoice->isPaid())
                {{-- Sudah lunas, tidak perlu form pembayaran --}}
                <div class="text-center mt-4 text-muted">
                    <i class="fas fa-check-circle text-success fa-3x mb-2"></i>
                    <p class="mb-0">Pembayaran Anda telah dikonfirmasi. Terima kasih!</p>
                    @if($settings && $settings->business_phone)
                        <p class="small mt-2">Hubungi kami: {{ $settings->business_phone }}</p>
                    @endif
                </div>

            @else

                {{-- Cek apakah ada pending payment --}}
                @php
                    $pendingPayment = $invoice->payments()->where('status', 'pending')->first();
                @endphp

                @if($pendingPayment)
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Bukti transfer sudah dikirim.</strong> Admin sedang memverifikasi pembayaran Anda. Proses konfirmasi biasanya dilakukan dalam 1x24 jam.
                    </div>
                @else

                    <div class="section-divider"></div>
                    <h6 class="font-weight-bold mb-3"><i class="fas fa-credit-card mr-2"></i>Pilih Metode Pembayaran</h6>

                    {{-- Tab navigasi --}}
                    @php
                        $hasGateway = !empty($groupedChannels);
                        $hasManual  = $settings && $settings->enable_manual_payment && $bankAccounts->count() > 0;
                        $activeTab  = $hasGateway ? 'gateway' : 'manual';
                    @endphp

                    @if($hasGateway || $hasManual)
                        <ul class="nav nav-tabs payment-tab mb-3" id="paymentTab">
                            @if($hasGateway)
                                <li class="nav-item">
                                    <a class="nav-link {{ $activeTab === 'gateway' ? 'active' : '' }}" data-toggle="tab" href="#tab-gateway">
                                        <i class="fas fa-qrcode mr-1"></i>QRIS / VA
                                    </a>
                                </li>
                            @endif
                            @if($hasManual)
                                <li class="nav-item">
                                    <a class="nav-link {{ $activeTab === 'manual' ? 'active' : '' }}" data-toggle="tab" href="#tab-manual">
                                        <i class="fas fa-university mr-1"></i>Transfer Manual
                                    </a>
                                </li>
                            @endif
                        </ul>

                        <div class="tab-content">

                            {{-- Tab Gateway --}}
                            @if($hasGateway)
                            <div class="tab-pane fade {{ $activeTab === 'gateway' ? 'show active' : '' }}" id="tab-gateway">
                                <form action="{{ route('customer.invoice.gateway', $token) }}" method="POST">
                                    @csrf
                                    @foreach($groupedChannels as $groupKey => $group)
                                        <p class="font-weight-bold text-muted small text-uppercase mb-2">{{ $group['name'] }}</p>
                                        <div class="row mb-3">
                                            @foreach($group['channels'] as $ch)
                                                <div class="col-6 col-md-4 mb-2">
                                                    <label class="d-block border rounded p-2 text-center channel-label" style="cursor:pointer;">
                                                        <input type="radio" name="payment_channel" value="{{ $ch['code'] }}" class="d-none channel-radio" required>
                                                        @if(!empty($ch['icon_url']))
                                                            <img src="{{ $ch['icon_url'] }}" alt="{{ $ch['name'] }}" style="height:32px; object-fit:contain;" class="d-block mx-auto mb-1">
                                                        @endif
                                                        <span class="small">{{ $ch['name'] }}</span>
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-arrow-right mr-2"></i>Lanjut Bayar
                                    </button>
                                </form>
                            </div>
                            @endif

                            {{-- Tab Manual Transfer --}}
                            @if($hasManual)
                            <div class="tab-pane fade {{ $activeTab === 'manual' ? 'show active' : '' }}" id="tab-manual">

                                {{-- Info rekening --}}
                                @if($bankAccounts->count() > 0)
                                    <div class="alert alert-light border mb-3">
                                        <p class="font-weight-bold mb-1"><i class="fas fa-university mr-1"></i>Rekening Tujuan Transfer</p>
                                        @foreach($bankAccounts as $bank)
                                            <div class="{{ !$loop->last ? 'mb-2 pb-2 border-bottom' : '' }}">
                                                <div class="font-weight-bold">{{ $bank->bank_name }}</div>
                                                <div>{{ $bank->account_number }} a/n {{ $bank->account_name }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <form action="{{ route('customer.invoice.manual', $token) }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="form-group">
                                        <label>Bank Tujuan yang Digunakan <span class="text-danger">*</span></label>
                                        <select name="bank_account_id" class="form-control" required>
                                            <option value="">-- Pilih Rekening --</option>
                                            @foreach($bankAccounts as $bank)
                                                <option value="{{ $bank->id }}" {{ old('bank_account_id') == $bank->id ? 'selected' : '' }}>
                                                    {{ $bank->bank_name }} - {{ $bank->account_number }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Jumlah yang Ditransfer <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                            <input type="number" name="amount_transferred" class="form-control" required min="0"
                                                value="{{ old('amount_transferred', (int) $invoice->total) }}" placeholder="{{ (int) $invoice->total }}">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Tanggal Transfer <span class="text-danger">*</span></label>
                                        <input type="date" name="transfer_date" class="form-control" required
                                            value="{{ old('transfer_date', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}">
                                    </div>
                                    <div class="form-group">
                                        <label>Bukti Transfer <span class="text-danger">*</span></label>
                                        <input type="file" name="payment_proof" class="form-control-file" required
                                            accept="image/jpeg,image/png,image/gif" id="proof-input">
                                        <small class="text-muted">Format: JPG, PNG, GIF. Maks. 5MB.</small>
                                        <div id="proof-preview-wrap" class="mt-2" style="display:none;">
                                            <img id="proof-preview" class="proof-preview" src="" alt="Preview">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Catatan <span class="text-muted small">(opsional)</span></label>
                                        <input type="text" name="notes" class="form-control" placeholder="mis. transfer dari BCA ke BRI"
                                            value="{{ old('notes') }}" maxlength="500">
                                    </div>
                                    <button type="submit" class="btn btn-success btn-block">
                                        <i class="fas fa-paper-plane mr-2"></i>Kirim Bukti Transfer
                                    </button>
                                </form>
                            </div>
                            @endif

                        </div>
                    @else
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Tidak ada metode pembayaran yang tersedia. Silakan hubungi admin.
                        </div>
                    @endif

                @endif {{-- end not pendingPayment --}}
            @endif {{-- end not paid --}}

            {{-- Footer --}}
            <div class="section-divider"></div>
            <div class="text-center text-muted small">
                @if($settings && $settings->business_name)
                    <strong>{{ $settings->business_name }}</strong><br>
                @endif
                @if($settings && $settings->business_phone)
                    <i class="fas fa-phone-alt mr-1"></i>{{ $settings->business_phone }}
                @endif
                @if($settings && $settings->business_email)
                    &nbsp;·&nbsp;<i class="fas fa-envelope mr-1"></i>{{ $settings->business_email }}
                @endif
                @if($serverLoadTimeMs !== null)
                    <div class="mt-2">Load Time: {{ number_format($serverLoadTimeMs, 1, '.', '') }} ms</div>
                @endif
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Preview gambar sebelum upload
    document.getElementById('proof-input')?.addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            var preview = document.getElementById('proof-preview');
            var wrap    = document.getElementById('proof-preview-wrap');
            preview.src = e.target.result;
            wrap.style.display = '';
        };
        reader.readAsDataURL(file);
    });

    // Highlight channel yang dipilih
    document.querySelectorAll('.channel-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.channel-label').forEach(function (lbl) {
                lbl.classList.remove('border-primary', 'bg-light');
            });
            if (this.checked) {
                this.closest('.channel-label').classList.add('border-primary', 'bg-light');
            }
        });
    });
</script>
</body>
</html>
