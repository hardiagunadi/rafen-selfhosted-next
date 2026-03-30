@extends('layouts.admin')

@section('title', 'Kelola Tenant')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Daftar Tenant</h3>
        <div class="card-tools">
            <a href="{{ route('super-admin.tenants.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Tambah Tenant
            </a>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama, email, perusahaan..." value="{{ request('search') }}">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-default">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="trial" {{ request('status') == 'trial' ? 'selected' : '' }}>Trial</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="expired" {{ request('status') == 'expired' ? 'selected' : '' }}>Berakhir</option>
                        <option value="suspended" {{ request('status') == 'suspended' ? 'selected' : '' }}>Suspend</option>
                    </select>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Perusahaan</th>
                        <th>Metode</th>
                        <th>Paket</th>
                        <th>Status</th>
                        <th>Berakhir</th>
                        <th>VPN</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tenants as $tenant)
                    <tr>
                        <td>
                            <a href="{{ route('super-admin.tenants.show', $tenant) }}">
                                {{ $tenant->name }}
                            </a>
                        </td>
                        <td>{{ $tenant->email }}</td>
                        <td>{{ $tenant->company_name ?? '-' }}</td>
                        <td>
                            <span class="badge {{ $tenant->subscription_method === 'license' ? 'badge-info' : 'badge-primary' }}">
                                {{ $tenant->subscription_method === 'license' ? 'Lisensi' : 'Bulanan' }}
                            </span>
                        </td>
                        <td>{{ $tenant->subscriptionPlan->name ?? '-' }}</td>
                        <td>
                            @switch($tenant->subscription_status)
                                @case('trial')
                                    <span class="badge badge-warning">Trial ({{ $tenant->trial_days_remaining }}d)</span>
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
                        </td>
                        <td>{{ $tenant->subscription_expires_at ? $tenant->subscription_expires_at->format('d M Y') : '-' }}</td>
                        <td>
                            @if($tenant->vpn_enabled)
                                <span class="badge badge-success"><i class="fas fa-check"></i></span>
                            @else
                                <span class="badge badge-secondary"><i class="fas fa-times"></i></span>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="btn-group">
                                <a href="{{ route('super-admin.tenants.show', $tenant) }}" class="btn btn-xs btn-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('super-admin.tenants.edit', $tenant) }}" class="btn btn-xs btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted">Tidak ada data tenant</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($tenants->hasPages())
    <div class="card-footer">
        {{ $tenants->links() }}
    </div>
    @endif
</div>
@endsection
