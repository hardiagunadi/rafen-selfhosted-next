@extends('layouts.admin')

@section('title', 'Saldo Wallet Tenant')

@section('content')
<div class="container-fluid">
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h4 class="mb-0"><i class="fas fa-wallet mr-2 text-primary"></i>Saldo Wallet Tenant</h4>
            <small class="text-muted">Saldo wallet semua tenant yang menggunakan platform gateway</small>
        </div>
        <div class="col-auto">
            <a href="{{ route('super-admin.withdrawals.index') }}" class="btn btn-primary">
                <i class="fas fa-money-bill-wave mr-1"></i> Permintaan Penarikan
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Tenant</th>
                            <th>Perusahaan</th>
                            <th class="text-right">Saldo Tersedia</th>
                            <th class="text-right">Total Masuk</th>
                            <th class="text-right">Total Ditarik</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($wallets as $wallet)
                        <tr>
                            <td>
                                <strong>{{ $wallet->owner?->name ?? 'N/A' }}</strong>
                                <br><small class="text-muted">{{ $wallet->owner?->email }}</small>
                            </td>
                            <td>{{ $wallet->owner?->company_name ?? '-' }}</td>
                            <td class="text-right">
                                <strong class="{{ $wallet->balance > 0 ? 'text-success' : 'text-muted' }}">
                                    Rp {{ number_format($wallet->balance, 0, ',', '.') }}
                                </strong>
                            </td>
                            <td class="text-right text-muted">Rp {{ number_format($wallet->total_credited, 0, ',', '.') }}</td>
                            <td class="text-right text-muted">Rp {{ number_format($wallet->total_withdrawn, 0, ',', '.') }}</td>
                            <td class="text-center">
                                <a href="{{ route('super-admin.withdrawals.index') }}?owner={{ $wallet->owner_id }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-history"></i> Riwayat
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="fas fa-wallet fa-2x d-block mb-2"></i>
                                Belum ada tenant yang memiliki saldo wallet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                    @if($wallets->count() > 0)
                    <tfoot class="thead-light">
                        <tr>
                            <th colspan="2" class="text-right">Total</th>
                            <th class="text-right text-success font-weight-bold">Rp {{ number_format($wallets->sum('balance'), 0, ',', '.') }}</th>
                            <th class="text-right">Rp {{ number_format($wallets->sum('total_credited'), 0, ',', '.') }}</th>
                            <th class="text-right">Rp {{ number_format($wallets->sum('total_withdrawn'), 0, ',', '.') }}</th>
                            <th></th>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
