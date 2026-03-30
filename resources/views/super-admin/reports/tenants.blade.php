@extends('layouts.admin')

@section('title', 'Laporan Tenant')

@section('content')
<div class="container-fluid">
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h4 class="mb-0"><i class="fas fa-chart-bar mr-2 text-primary"></i>Laporan Tenant</h4>
            <small class="text-muted">Statistik dan distribusi tenant pada platform</small>
        </div>
    </div>

    {{-- Stat Cards by Status --}}
    <div class="row">
        @php
            $statusConfig = [
                'trial'     => ['label' => 'Trial',     'color' => 'warning', 'icon' => 'fas fa-hourglass-half'],
                'active'    => ['label' => 'Aktif',     'color' => 'success', 'icon' => 'fas fa-check-circle'],
                'expired'   => ['label' => 'Berakhir',  'color' => 'danger',  'icon' => 'fas fa-times-circle'],
                'suspended' => ['label' => 'Suspend',   'color' => 'secondary','icon' => 'fas fa-ban'],
            ];
        @endphp
        @foreach($statusConfig as $status => $cfg)
        <div class="col-6 col-md-3">
            <div class="small-box bg-{{ $cfg['color'] }}">
                <div class="inner">
                    <h3>{{ $tenantsByStatus[$status] ?? 0 }}</h3>
                    <p>Tenant {{ $cfg['label'] }}</p>
                </div>
                <div class="icon">
                    <i class="{{ $cfg['icon'] }}"></i>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Info Boxes: New & Churn --}}
    <div class="row">
        <div class="col-md-6">
            <div class="info-box">
                <span class="info-box-icon bg-info"><i class="fas fa-user-plus"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Tenant Baru Bulan Ini</span>
                    <span class="info-box-number">{{ $newTenantsThisMonth }}</span>
                    <span class="progress-description">{{ now()->translatedFormat('F Y') }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="info-box">
                <span class="info-box-icon bg-danger"><i class="fas fa-user-minus"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Churn Bulan Ini</span>
                    <span class="info-box-number">{{ $churnedThisMonth }}</span>
                    <span class="progress-description">Langganan berakhir {{ now()->translatedFormat('F Y') }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Top Tenants Table --}}
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-trophy mr-1 text-warning"></i> Top 10 Tenant (berdasarkan jumlah pelanggan PPP)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th width="40">#</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Perusahaan</th>
                            <th>Paket</th>
                            <th>Status</th>
                            <th class="text-right">Jumlah PPP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topTenants as $i => $tenant)
                        @php
                            $statusMap = [
                                'trial'     => ['label' => 'Trial',    'class' => 'badge-warning'],
                                'active'    => ['label' => 'Aktif',    'class' => 'badge-success'],
                                'expired'   => ['label' => 'Berakhir', 'class' => 'badge-danger'],
                                'suspended' => ['label' => 'Suspend',  'class' => 'badge-secondary'],
                            ];
                            $st = $statusMap[$tenant->subscription_status] ?? ['label' => $tenant->subscription_status, 'class' => 'badge-light'];
                        @endphp
                        <tr>
                            <td>
                                @if($i === 0) <i class="fas fa-medal text-warning"></i>
                                @elseif($i === 1) <i class="fas fa-medal text-secondary"></i>
                                @elseif($i === 2) <i class="fas fa-medal" style="color:#cd7f32"></i>
                                @else {{ $i + 1 }}
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('super-admin.tenants.show', $tenant) }}">{{ $tenant->name }}</a>
                            </td>
                            <td>{{ $tenant->email }}</td>
                            <td>{{ $tenant->tenantSettings?->company_name ?? '-' }}</td>
                            <td>{{ $tenant->activeSubscription?->subscriptionPlan?->name ?? '-' }}</td>
                            <td><span class="badge {{ $st['class'] }}">{{ $st['label'] }}</span></td>
                            <td class="text-right"><strong>{{ number_format($tenant->ppp_users_count) }}</strong></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Belum ada data tenant.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
