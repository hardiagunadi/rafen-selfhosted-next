@extends('layouts.admin')

@section('title', 'Wallet Saldo')

@section('content')
<div class="container-fluid">
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h4 class="mb-0"><i class="fas fa-wallet mr-2 text-primary"></i>Wallet Saldo</h4>
            <small class="text-muted">Saldo dari pembayaran pelanggan via platform gateway</small>
        </div>
        <div class="col-auto">
            <a href="{{ route('wallet.withdrawals.index') }}" class="btn btn-outline-secondary mr-2">
                <i class="fas fa-history mr-1"></i> Riwayat Penarikan
            </a>
            @if(!$pendingWithdrawal && $wallet->balance >= 10000)
            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#modalWithdrawal">
                <i class="fas fa-arrow-circle-down mr-1"></i> Minta Penarikan
            </button>
            @elseif($pendingWithdrawal)
            <button class="btn btn-warning" disabled>
                <i class="fas fa-clock mr-1"></i> Ada Penarikan Diproses
            </button>
            @else
            <button class="btn btn-secondary" disabled>
                <i class="fas fa-arrow-circle-down mr-1"></i> Saldo Tidak Cukup
            </button>
            @endif
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
    @endif

    {{-- Balance Cards --}}
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3 class="font-weight-bold">Rp {{ number_format($wallet->balance, 0, ',', '.') }}</h3>
                    <p>Saldo Tersedia</p>
                </div>
                <div class="icon"><i class="fas fa-wallet"></i></div>
                <a href="#" class="small-box-footer" data-toggle="modal" data-target="#modalWithdrawal">
                    {{ $pendingWithdrawal ? 'Ada permintaan diproses' : 'Tarik Saldo' }} <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>Rp {{ number_format($wallet->total_credited, 0, ',', '.') }}</h3>
                    <p>Total Masuk (Lifetime)</p>
                </div>
                <div class="icon"><i class="fas fa-arrow-circle-down"></i></div>
                <span class="small-box-footer">&nbsp;</span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-secondary">
                <div class="inner">
                    <h3>Rp {{ number_format($wallet->total_withdrawn, 0, ',', '.') }}</h3>
                    <p>Total Ditarik (Lifetime)</p>
                </div>
                <div class="icon"><i class="fas fa-arrow-circle-up"></i></div>
                <span class="small-box-footer">&nbsp;</span>
            </div>
        </div>
    </div>

    @if($pendingWithdrawal)
    <div class="alert alert-warning">
        <i class="fas fa-clock mr-1"></i>
        Anda memiliki permintaan penarikan yang sedang diproses. Silakan tunggu konfirmasi dari tim kami.
        <a href="{{ route('wallet.withdrawals.index') }}" class="alert-link ml-2">Lihat Status</a>
    </div>
    @endif

    {{-- Recent Transactions --}}
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h5 class="mb-0 mr-auto"><i class="fas fa-list mr-1"></i> Transaksi Terbaru</h5>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0" id="tableTransactions">
                <thead class="thead-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Tipe</th>
                        <th class="text-right">Jumlah</th>
                        <th class="text-right">Fee Dipotong</th>
                        <th class="text-right">Saldo Setelah</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentTransactions as $txn)
                    <tr>
                        <td>{{ $txn->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            @if($txn->type === 'credit')
                                <span class="badge badge-success">Masuk</span>
                            @else
                                <span class="badge badge-secondary">Keluar</span>
                            @endif
                        </td>
                        <td class="text-right {{ $txn->type === 'credit' ? 'text-success' : 'text-danger' }}">
                            {{ $txn->type === 'credit' ? '+' : '-' }}Rp {{ number_format($txn->amount, 0, ',', '.') }}
                        </td>
                        <td class="text-right text-muted">
                            {{ $txn->fee_deducted > 0 ? 'Rp ' . number_format($txn->fee_deducted, 0, ',', '.') : '-' }}
                        </td>
                        <td class="text-right font-weight-bold">
                            Rp {{ number_format($txn->balance_after, 0, ',', '.') }}
                        </td>
                        <td>{{ $txn->description }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                            Belum ada transaksi wallet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($recentTransactions->count() >= 10)
        <div class="card-footer text-right">
            <small class="text-muted">Menampilkan 10 transaksi terbaru.</small>
        </div>
        @endif
    </div>
</div>

{{-- Modal Withdrawal --}}
<div class="modal fade" id="modalWithdrawal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('wallet.withdrawal.request') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-arrow-circle-down mr-1"></i> Permintaan Penarikan</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 px-3 small">
                        <i class="fas fa-info-circle mr-1"></i>
                        Saldo tersedia: <strong>Rp {{ number_format($wallet->balance, 0, ',', '.') }}</strong>.
                        Transfer akan dilakukan secara manual oleh tim kami dalam 1-3 hari kerja.
                    </div>

                    <div class="form-group">
                        <label>Jumlah Penarikan <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                            <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror"
                                min="10000" max="{{ $wallet->balance }}" step="1000"
                                value="{{ old('amount') }}" placeholder="Masukkan jumlah" required>
                        </div>
                        <small class="text-muted">Minimal Rp 10.000, maksimal Rp {{ number_format($wallet->balance, 0, ',', '.') }}</small>
                        @error('amount')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <hr>
                    <h6>Rekening Tujuan</h6>

                    <div class="form-group">
                        <label>Nama Bank <span class="text-danger">*</span></label>
                        <input type="text" name="bank_name" class="form-control @error('bank_name') is-invalid @enderror"
                            value="{{ old('bank_name', $primaryBankAccount?->bank_name) }}"
                            placeholder="cth: BCA, BRI, Mandiri" required>
                        @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label>Nomor Rekening <span class="text-danger">*</span></label>
                        <input type="text" name="bank_account_number" class="form-control @error('bank_account_number') is-invalid @enderror"
                            value="{{ old('bank_account_number', $primaryBankAccount?->account_number) }}"
                            placeholder="cth: 1234567890" required>
                        @error('bank_account_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label>Nama Pemilik Rekening <span class="text-danger">*</span></label>
                        <input type="text" name="bank_account_name" class="form-control @error('bank_account_name') is-invalid @enderror"
                            value="{{ old('bank_account_name', $primaryBankAccount?->account_name) }}"
                            placeholder="Sesuai buku tabungan" required>
                        @error('bank_account_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" @if($pendingWithdrawal || $wallet->balance < 10000) disabled @endif>
                        <i class="fas fa-paper-plane mr-1"></i> Ajukan Penarikan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
@if($errors->any())
    $('#modalWithdrawal').modal('show');
@endif
</script>
@endpush
