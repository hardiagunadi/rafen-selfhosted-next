@extends('layouts.admin')

@section('title', 'Detail Tenant: ' . $tenant->name)

@section('content')
@if(session('new_tenant_subdomain_url'))
<div class="alert alert-success alert-dismissible d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3" role="alert">
    <div>
        <i class="fas fa-check-circle mr-2"></i>
        <strong>Tenant berhasil dibuat!</strong>
        Subdomain aktif di: <a href="{{ session('new_tenant_subdomain_url') }}" target="_blank" class="alert-link font-weight-bold">{{ session('new_tenant_subdomain_url') }}</a>
    </div>
    <a href="{{ session('new_tenant_subdomain_url') }}" target="_blank" class="btn btn-success btn-sm">
        <i class="fas fa-external-link-alt mr-1"></i> Buka Subdomain
    </a>
    <button type="button" class="close ml-2" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
@endif
<div class="row">
    <div class="col-md-4">
        <!-- Profile Card -->
        <div class="card card-primary card-outline">
            <div class="card-body box-profile">
                <h3 class="profile-username text-center">{{ $tenant->name }}</h3>
                <p class="text-muted text-center">{{ $tenant->company_name ?? 'Individual' }}</p>
                @if($tenant->isSelfHostedInstance())
                <div class="text-center mb-3">
                    <span class="badge badge-dark">Tenant Self-Hosted</span>
                </div>
                @endif

                <ul class="list-group list-group-unbordered mb-3">
                    <li class="list-group-item">
                        <b>Email</b> <a class="float-right">{{ $tenant->email }}</a>
                    </li>
                    <li class="list-group-item">
                        <b>Telepon</b> <a class="float-right">{{ $tenant->phone ?? '-' }}</a>
                    </li>
                    <li class="list-group-item">
                        <b>Status</b>
                        <span class="float-right">
                            @switch($tenant->subscription_status)
                                @case('trial')
                                    <span class="badge badge-warning">Trial</span>
                                    @break
                                @case('active')
                                    <span class="badge badge-success">Aktif</span>
                                    @break
                                @case('expired')
                                    <span class="badge badge-secondary">Berakhir</span>
                                    @break
                                @case('suspended')
                                    <span class="badge badge-danger">Suspend</span>
                                    @break
                            @endswitch
                        </span>
                    </li>
                    <li class="list-group-item">
                        <b>Metode</b>
                        <a class="float-right">
                            {{ $tenant->isLicenseSubscription() ? 'Lisensi Tahunan' : 'Bulanan' }}
                        </a>
                    </li>
                    @if($tenant->isSelfHostedInstance())
                    <li class="list-group-item">
                        <b>Instance</b>
                        <a class="float-right">{{ $tenant->self_hosted_instance_name ?: '-' }}</a>
                    </li>
                    <li class="list-group-item">
                        <b>License ID</b>
                        <a class="float-right">{{ $tenant->self_hosted_license_id ?: '-' }}</a>
                    </li>
                    <li class="list-group-item">
                        <b>Fingerprint</b>
                        <a class="float-right text-monospace">{{ $tenant->self_hosted_fingerprint ?: '-' }}</a>
                    </li>
                    <li class="list-group-item">
                        <b>App URL</b>
                        <a class="float-right">{{ $tenant->self_hosted_app_url ?: '-' }}</a>
                    </li>
                    @endif
                    <li class="list-group-item">
                        <b>Paket</b> <a class="float-right">{{ $tenant->subscriptionPlan->name ?? '-' }}</a>
                    </li>
                    @if($tenant->isLicenseSubscription())
                    <li class="list-group-item">
                        <b>Limit Mikrotik</b>
                        <a class="float-right">
                            {{ $tenant->license_max_mikrotik === -1 ? 'Unlimited' : ($tenant->license_max_mikrotik ?? '-') }}
                        </a>
                    </li>
                    <li class="list-group-item">
                        <b>Limit PPP Users</b>
                        <a class="float-right">
                            {{ $tenant->license_max_ppp_users === -1 ? 'Unlimited' : ($tenant->license_max_ppp_users ?? '-') }}
                        </a>
                    </li>
                    @endif
                    <li class="list-group-item">
                        <b>Berakhir</b>
                        <a class="float-right">
                            {{ $tenant->subscription_expires_at ? $tenant->subscription_expires_at->format('d M Y') : '-' }}
                        </a>
                    </li>
                    <li class="list-group-item">
                        <b>Terdaftar</b>
                        <a class="float-right">{{ $tenant->created_at->format('d M Y') }}</a>
                    </li>
                </ul>

                <div class="btn-group btn-group-sm d-flex">
                    <a href="{{ route('super-admin.tenants.edit', $tenant) }}" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="{{ route('super-admin.tenants.vpn', $tenant) }}" class="btn btn-info">
                        <i class="fas fa-network-wired"></i> VPN
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Statistik</h3>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Mikrotik</span>
                        <strong>{{ $stats['mikrotik_count'] }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>PPP Users</span>
                        <strong>{{ $stats['ppp_users_count'] }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>PPP Aktif</span>
                        <strong>{{ $stats['active_ppp_users'] }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Hotspot Users</span>
                        <strong>{{ $stats['hotspot_users_count'] }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Hotspot Aktif</span>
                        <strong>{{ $stats['active_hotspot_users'] }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Invoice</span>
                        <strong>{{ $stats['invoices_count'] }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Belum Bayar</span>
                        <strong class="text-danger">{{ $stats['unpaid_invoices'] }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Total Pendapatan</span>
                        <strong class="text-success">Rp {{ number_format($stats['total_revenue'], 0, ',', '.') }}</strong>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Aksi Cepat</h3>
            </div>
            @php
                $isLicenseMethod = $tenant->isLicenseSubscription();
                $activateActionLabel = $isLicenseMethod ? 'Aktifkan Lisensi' : 'Aktifkan Langganan';
                $extendActionLabel = $isLicenseMethod ? 'Perpanjang Lisensi' : 'Perpanjang';
                $activateModalTitle = $isLicenseMethod ? 'Aktifkan Lisensi Tahunan' : 'Aktifkan Langganan';
                $extendModalTitle = $isLicenseMethod ? 'Perpanjang Lisensi Tahunan' : 'Perpanjang Langganan';
                $activePppUsers = (int) ($stats['active_ppp_users'] ?? 0);
                $activeHotspotUsers = (int) ($stats['active_hotspot_users'] ?? 0);
                $activeCustomerCount = $activePppUsers + $activeHotspotUsers;
            @endphp
            <div class="card-body">
                @if($tenant->subscription_status !== 'active')
                <button type="button" class="btn btn-success btn-block mb-2" data-toggle="modal" data-target="#activateModal">
                    <i class="fas fa-check"></i> {{ $activateActionLabel }}
                </button>
                @endif

                @if($tenant->subscription_status === 'active')
                <button type="button" class="btn btn-info btn-block mb-2" data-toggle="modal" data-target="#extendModal">
                    <i class="fas fa-plus"></i> {{ $extendActionLabel }}
                </button>
                @endif

                <button type="button" class="btn btn-primary btn-block mb-2" data-toggle="modal" data-target="#changePlanModal">
                    <i class="fas fa-exchange-alt"></i> Ubah Paket
                </button>

                @if($tenant->subscription_status !== 'suspended')
                <form action="{{ route('super-admin.tenants.suspend', $tenant) }}" method="POST" onsubmit="return confirm('Suspend tenant ini?')">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-block mb-2">
                        <i class="fas fa-pause"></i> Suspend
                    </button>
                </form>
                @endif

                <button type="button" class="btn btn-danger btn-block" data-toggle="modal" data-target="#deleteTenantModal">
                    <i class="fas fa-trash"></i> Hapus Tenant
                </button>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <!-- Pending Subscriptions (awaiting manual payment confirmation) -->
        @if($pendingSubscriptions->isNotEmpty())
        <div class="card card-warning card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-clock mr-1"></i> Menunggu Konfirmasi Pembayaran</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Paket</th>
                            <th>Mulai</th>
                            <th>Berakhir</th>
                            <th>Jumlah</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingSubscriptions as $sub)
                        <tr>
                            <td>{{ $sub->plan->name ?? '-' }}</td>
                            <td>{{ $sub->start_date->format('d M Y') }}</td>
                            <td>{{ $sub->end_date->format('d M Y') }}</td>
                            <td>Rp {{ number_format($sub->amount_paid, 0, ',', '.') }}</td>
                            <td>
                                <button type="button" class="btn btn-success btn-xs"
                                    data-toggle="modal"
                                    data-target="#confirmPaymentModal{{ $sub->id }}">
                                    <i class="fas fa-check"></i> Konfirmasi
                                </button>
                                <button type="button" class="btn btn-danger btn-xs ml-1"
                                    data-toggle="modal"
                                    data-target="#deleteSubscriptionModal{{ $sub->id }}">
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <!-- Subscription History -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Riwayat Langganan</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Paket</th>
                            <th>Mulai</th>
                            <th>Berakhir</th>
                            <th>Status</th>
                            <th>Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tenant->subscriptions as $sub)
                        <tr>
                            <td>{{ $sub->plan->name ?? '-' }}</td>
                            <td>{{ $sub->start_date->format('d M Y') }}</td>
                            <td>{{ $sub->end_date->format('d M Y') }}</td>
                            <td>
                                <span class="badge badge-{{ $sub->status === 'active' ? 'success' : 'secondary' }}">
                                    {{ $sub->status }}
                                </span>
                            </td>
                            <td>Rp {{ number_format($sub->amount_paid, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">Tidak ada data</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mikrotik Connections -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Mikrotik Connections</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Host</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tenant->mikrotikConnections as $mt)
                        <tr>
                            <td>{{ $mt->name }}</td>
                            <td>{{ $mt->host }}</td>
                            <td>
                                @if($mt->is_online)
                                    <span class="badge badge-success">Online</span>
                                @else
                                    <span class="badge badge-danger">Offline</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted">Tidak ada Mikrotik</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Daftar Role Tenant</h3>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap" style="gap:.5rem;">
                    @forelse($tenantRoles as $tenantRole)
                        <span class="badge badge-info p-2">
                            {{ $tenantRole['label'] }}
                            <span class="badge badge-light ml-1">{{ $tenantRole['total'] }}</span>
                        </span>
                    @empty
                        <span class="text-muted small">Belum ada role terdaftar.</span>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- VPN Info -->
        @if($tenant->vpn_enabled)
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Informasi VPN</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Username:</strong><br>
                        <code>{{ $tenant->vpn_username }}</code>
                    </div>
                    <div class="col-md-4">
                        <strong>Password:</strong><br>
                        <code>{{ $tenant->vpn_password }}</code>
                    </div>
                    <div class="col-md-4">
                        <strong>IP Address:</strong><br>
                        <code>{{ $tenant->vpn_ip }}</code>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Confirm Payment Modals (one per pending subscription) -->
@foreach($pendingSubscriptions as $sub)
<div class="modal fade" id="confirmPaymentModal{{ $sub->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('super-admin.tenants.subscriptions.confirm-payment', [$tenant, $sub]) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-check-circle text-success mr-1"></i> Konfirmasi Pembayaran Manual</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Paket: <strong>{{ $sub->plan->name ?? '-' }}</strong> — <strong>Rp {{ number_format($sub->amount_paid, 0, ',', '.') }}</strong></p>
                    <div class="form-group">
                        <label>Metode Pembayaran</label>
                        <input type="text" name="payment_method" class="form-control" placeholder="Contoh: Transfer BCA, Tunai, dll" value="Transfer Manual">
                    </div>
                    <div class="form-group">
                        <label>Catatan <small class="text-muted">(opsional)</small></label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan tambahan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Konfirmasi Bayar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

<!-- Delete Subscription Modals (one per pending subscription) -->
@foreach($pendingSubscriptions as $sub)
<div class="modal fade" id="deleteSubscriptionModal{{ $sub->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('super-admin.tenants.subscriptions.delete', [$tenant, $sub]) }}" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-trash text-danger mr-1"></i> Hapus Langganan Pending</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Yakin ingin menghapus langganan pending ini?</p>
                    <p class="text-muted mb-1">Paket: <strong>{{ $sub->plan->name ?? '-' }}</strong></p>
                    <p class="text-muted mb-0">Jumlah: <strong>Rp {{ number_format($sub->amount_paid, 0, ',', '.') }}</strong></p>
                    <div class="alert alert-warning mt-3 mb-0">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Data pembayaran terkait yang masih <strong>pending</strong> juga akan ikut dihapus.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash mr-1"></i> Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

<!-- Change Plan Modal (Upgrade/Downgrade) -->
<div class="modal fade" id="changePlanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('super-admin.tenants.change-plan', $tenant) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exchange-alt mr-1"></i> Ubah Paket Langganan</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 p-2 bg-light rounded">
                        <small class="text-muted d-block">Paket saat ini: <strong>{{ $tenant->subscriptionPlan->name ?? 'Tidak ada' }}</strong></small>
                        <small class="text-muted d-block">Berakhir: <strong>{{ $tenant->subscription_expires_at ? $tenant->subscription_expires_at->format('d M Y') : '-' }}</strong></small>
                    </div>
                    <div class="form-group">
                        <label>Pilih Paket Baru</label>
                        <select name="plan_id" id="changePlanSelect" class="form-control" required>
                            @foreach(\App\Models\SubscriptionPlan::active()->orderBy('sort_order')->get() as $plan)
                            <option value="{{ $plan->id }}" {{ $tenant->subscription_plan_id == $plan->id ? 'selected' : '' }}>
                                {{ $plan->name }} - Rp {{ number_format($plan->price, 0, ',', '.') }} - {{ $tenant->resolveSubscriptionDurationDays($plan) }} hari{{ $tenant->isLicenseSubscription() ? ' (Lisensi)' : '' }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Prorated Preview -->
                    <div id="proratedPreview" class="d-none">
                        <div class="card card-body bg-light mb-3 p-3">
                            <h6 class="mb-2"><i class="fas fa-calculator mr-1"></i> Kalkulasi Prorated</h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted pl-0">Sisa hari aktif</td>
                                    <td class="text-right font-weight-bold" id="previewRemainingDays">-</td>
                                </tr>
                                <tr>
                                    <td class="text-muted pl-0">Nilai sisa (kredit)</td>
                                    <td class="text-right" id="previewRemainingValue">-</td>
                                </tr>
                                <tr>
                                    <td class="text-muted pl-0">Harga paket baru</td>
                                    <td class="text-right" id="previewNewPrice">-</td>
                                </tr>
                                <tr class="border-top">
                                    <td class="pl-0"><strong>Tagihan prorated</strong></td>
                                    <td class="text-right font-weight-bold text-primary" id="previewProratedCost">-</td>
                                </tr>
                                <tr id="previewExtraDaysRow" class="d-none">
                                    <td class="text-muted pl-0">Bonus hari (sisa kredit)</td>
                                    <td class="text-right text-success font-weight-bold" id="previewExtraDays">-</td>
                                </tr>
                                <tr>
                                    <td class="text-muted pl-0">Total durasi baru</td>
                                    <td class="text-right" id="previewTotalDuration">-</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div id="proratedLoading" class="text-center text-muted py-2 d-none">
                        <i class="fas fa-spinner fa-spin mr-1"></i> Menghitung...
                    </div>

                    @php $activeGateway = \App\Models\PaymentGateway::active()->first(); @endphp
                    @if($activeGateway)
                    <div class="alert alert-info py-2 px-3 mb-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Payment gateway aktif: <strong>{{ $activeGateway->name }}</strong>.
                        Jika ada tagihan prorated, tenant akan menerima link pembayaran via WA & email.
                    </div>
                    @else
                    <div class="form-group">
                        <label>Metode Pembayaran <small class="text-muted">(untuk tagihan prorated)</small></label>
                        <input type="text" name="payment_method" class="form-control" placeholder="Contoh: Transfer BCA, Tunai" value="Transfer Manual">
                    </div>
                    @endif
                    <div class="form-group">
                        <label>Catatan <small class="text-muted">(opsional)</small></label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan tambahan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-exchange-alt"></i> Ubah Paket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Activate Modal -->
<div class="modal fade" id="activateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('super-admin.tenants.activate', $tenant) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ $activateModalTitle }}</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Pilih Paket</label>
                        <select name="plan_id" class="form-control" required>
                            @foreach(\App\Models\SubscriptionPlan::active()->get() as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }} - Rp {{ number_format($plan->price, 0, ',', '.') }} - {{ $tenant->resolveSubscriptionDurationDays($plan) }} hari{{ $tenant->isLicenseSubscription() ? ' (Lisensi)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($tenant->isLicenseSubscription())
                    <div class="alert alert-info mb-0">
                        Tenant ini menggunakan metode lisensi tahunan. Durasi aktivasi otomatis 365 hari.
                    </div>
                    @else
                    <div class="form-group">
                        <label>Durasi (hari)</label>
                        <input type="number" name="duration_days" class="form-control" placeholder="Kosongkan untuk default paket">
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">{{ $activateActionLabel }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Extend Modal -->
<div class="modal fade" id="extendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('super-admin.tenants.extend', $tenant) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ $extendModalTitle }}</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    @if($tenant->isLicenseSubscription())
                    <input type="hidden" name="days" value="365">
                    <div class="alert alert-info mb-0">
                        Tenant ini menggunakan metode lisensi tahunan. Perpanjangan akan menambah 365 hari.
                    </div>
                    @else
                    <div class="form-group">
                        <label>Tambah Hari</label>
                        <input type="number" name="days" class="form-control" value="30" min="1" max="365" required>
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info">{{ $extendActionLabel }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@push('scripts')
<script>
// Change plan prorated preview
var previewUrl = '{{ route("super-admin.tenants.change-plan.preview", $tenant) }}';
var previewTimer = null;

function formatRp(val) {
    return 'Rp ' + Math.round(val).toLocaleString('id-ID');
}

function loadProratedPreview(planId) {
    $('#proratedPreview').addClass('d-none');
    $('#proratedLoading').removeClass('d-none');

    $.get(previewUrl, { plan_id: planId }, function(data) {
        $('#proratedLoading').addClass('d-none');
        $('#previewRemainingDays').text(data.remaining_days + ' hari');
        $('#previewRemainingValue').text(formatRp(data.remaining_value));
        $('#previewNewPrice').text(formatRp(data.new_plan_price));
        $('#previewProratedCost').text(data.prorated_cost === 0 ? 'Rp 0 (kredit cukup)' : formatRp(data.prorated_cost));

        if (data.extra_days > 0) {
            $('#previewExtraDaysRow').removeClass('d-none');
            $('#previewExtraDays').text('+' + data.extra_days + ' hari bonus');
        } else {
            $('#previewExtraDaysRow').addClass('d-none');
        }
        $('#previewTotalDuration').text(data.total_duration + ' hari');
        $('#proratedPreview').removeClass('d-none');
    }).fail(function() {
        $('#proratedLoading').addClass('d-none');
    });
}

$('#changePlanModal').on('show.bs.modal', function() {
    var planId = $('#changePlanSelect').val();
    if (planId) loadProratedPreview(planId);
});

$('#changePlanSelect').on('change', function() {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(function() {
        loadProratedPreview($('#changePlanSelect').val());
    }, 300);
});

$('#deleteTenantEmailInput').on('input', function() {
    var expected = $(this).data('email');
    var match = $(this).val().trim() === expected;
    $('#deleteTenantConfirmBtn').prop('disabled', !match);
    $('#deleteTenantEmailError').toggleClass('d-none', match || $(this).val() === '');
});
</script>
@endpush

{{-- Modal Konfirmasi Hapus Tenant --}}
<div class="modal fade" id="deleteTenantModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle mr-2"></i>Hapus Tenant</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Tindakan ini akan <strong>menghapus permanen</strong> semua data tenant termasuk:</p>
                <ul class="small mb-3">
                    <li>{{ $stats['ppp_users_count'] }} pelanggan PPPoE &amp; {{ $stats['hotspot_users_count'] }} pelanggan Hotspot</li>
                    <li>Semua invoice, koneksi MikroTik, profil, dan pengaturan</li>
                    <li>Akun tenant dan semua sub-user</li>
                </ul>
                <p class="mb-1">Ketik email tenant untuk konfirmasi:</p>
                <p class="font-weight-bold text-danger mb-2">{{ $tenant->email }}</p>
                <input type="text" id="deleteTenantEmailInput" class="form-control" placeholder="Ketik email tenant..." data-email="{{ $tenant->email }}">
                <div id="deleteTenantEmailError" class="text-danger small mt-1 d-none">Email tidak cocok.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <form action="{{ route('super-admin.tenants.delete', $tenant) }}" method="POST" id="deleteTenantForm">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger" id="deleteTenantConfirmBtn" disabled>
                        <i class="fas fa-trash"></i> Hapus Permanen
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
