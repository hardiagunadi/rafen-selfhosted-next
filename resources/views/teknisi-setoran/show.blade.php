@extends('layouts.admin')

@section('title', 'Detail Setoran Teknisi')

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Info Setoran</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted" style="width:40%">Teknisi</td>
                        <td><strong>{{ $teknisiSetoran->teknisi?->name ?? '-' }}</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Tanggal</td>
                        <td><strong>{{ $teknisiSetoran->period_date->translatedFormat('d F Y') }}</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Jumlah Nota</td>
                        <td><strong>{{ $teknisiSetoran->total_invoices }}</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Total Tagihan</td>
                        <td><strong>Rp {{ number_format($teknisiSetoran->total_tagihan, 0, ',', '.') }}</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Total Tunai Setor</td>
                        <td><strong class="text-success">Rp {{ number_format($teknisiSetoran->total_cash, 0, ',', '.') }}</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status</td>
                        <td>
                            @if($teknisiSetoran->status === 'draft')
                                <span class="badge badge-secondary">Draft</span>
                            @elseif($teknisiSetoran->status === 'submitted')
                                <span class="badge badge-warning">Disubmit</span>
                            @else
                                <span class="badge badge-success">Terverifikasi</span>
                            @endif
                        </td>
                    </tr>
                    @if($teknisiSetoran->submitted_at)
                    <tr>
                        <td class="text-muted">Disubmit</td>
                        <td>{{ $teknisiSetoran->submitted_at->format('d/m/Y H:i') }}</td>
                    </tr>
                    @endif
                    @if($teknisiSetoran->status === 'verified')
                    <tr>
                        <td class="text-muted">Diverifikasi oleh</td>
                        <td>{{ $teknisiSetoran->verifiedBy?->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Waktu Verifikasi</td>
                        <td>{{ $teknisiSetoran->verified_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                    @if($teknisiSetoran->notes)
                    <tr>
                        <td class="text-muted">Catatan</td>
                        <td>{{ $teknisiSetoran->notes }}</td>
                    </tr>
                    @endif
                    @endif
                </table>

                <hr>

                @php $authUser = auth()->user(); @endphp

                {{-- Tombol Submit (teknisi) --}}
                @if($teknisiSetoran->status === 'draft' && ($authUser->isSuperAdmin() || $authUser->role === 'teknisi' && $teknisiSetoran->teknisi_id === $authUser->id || $authUser->isAdmin()))
                <form method="POST" action="{{ route('teknisi-setoran.submit', $teknisiSetoran) }}">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-block" onclick="return confirm('Submit setoran ke Keuangan?')">
                        <i class="fas fa-paper-plane mr-1"></i>Submit ke Keuangan
                    </button>
                </form>
                @endif

                {{-- Tombol Verifikasi (keuangan/admin) --}}
                @if($teknisiSetoran->status === 'submitted' && ($authUser->isSuperAdmin() || in_array($authUser->role, ['administrator', 'keuangan'])))
                <button type="button" class="btn btn-success btn-block" data-toggle="modal" data-target="#modal-verify">
                    <i class="fas fa-check-circle mr-1"></i>Verifikasi Setoran
                </button>
                @endif

                <a href="{{ route('teknisi-setoran.index') }}" class="btn btn-secondary btn-block mt-2">
                    <i class="fas fa-arrow-left mr-1"></i>Kembali
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Daftar Nota yang Dibayar</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Invoice</th>
                                <th>Pelanggan</th>
                                <th class="text-right">Total Tagihan</th>
                                <th class="text-right">Tunai</th>
                                <th class="text-right">Transfer</th>
                                <th>Catatan</th>
                                <th>Waktu Bayar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoices as $inv)
                            <tr>
                                <td>
                                    <a href="{{ route('invoices.show', $inv) }}" target="_blank" class="font-weight-bold">
                                        {{ $inv->invoice_number }}
                                    </a>
                                </td>
                                <td>
                                    <div>{{ $inv->customer_name }}</div>
                                    <div class="text-muted small">{{ $inv->customer_id }}</div>
                                </td>
                                <td class="text-right">Rp {{ number_format($inv->total, 0, ',', '.') }}</td>
                                <td class="text-right">
                                    @if($inv->cash_received)
                                        <span class="text-success font-weight-bold">Rp {{ number_format($inv->cash_received, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($inv->transfer_amount)
                                        Rp {{ number_format($inv->transfer_amount, 0, ',', '.') }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="small">{{ $inv->payment_note ?? '-' }}</td>
                                <td class="small">{{ $inv->paid_at?->format('H:i') ?? '-' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">Tidak ada nota pada periode ini.</td>
                            </tr>
                            @endforelse
                        </tbody>
                        @if($invoices->isNotEmpty())
                        <tfoot class="thead-light">
                            <tr>
                                <th colspan="2">Total</th>
                                <th class="text-right">Rp {{ number_format($invoices->sum('total'), 0, ',', '.') }}</th>
                                <th class="text-right text-success">Rp {{ number_format($invoices->sum('cash_received'), 0, ',', '.') }}</th>
                                <th class="text-right">Rp {{ number_format($invoices->sum('transfer_amount'), 0, ',', '.') }}</th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Verifikasi --}}
@if($teknisiSetoran->status === 'submitted' && (auth()->user()->isSuperAdmin() || in_array(auth()->user()->role, ['administrator', 'keuangan'])))
<div class="modal fade" id="modal-verify" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-check-circle text-success mr-2"></i>Verifikasi Setoran</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST" action="{{ route('teknisi-setoran.verify', $teknisiSetoran) }}">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>{{ $teknisiSetoran->teknisi?->name }}</strong> menyetorkan
                        <strong>Rp {{ number_format($teknisiSetoran->total_cash, 0, ',', '.') }}</strong>
                        dari <strong>{{ $teknisiSetoran->total_invoices }} nota</strong>
                        tanggal {{ $teknisiSetoran->period_date->translatedFormat('d F Y') }}.
                    </div>
                    <div class="form-group mb-0">
                        <label>Catatan Verifikasi <span class="text-muted small">(opsional)</span></label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="mis. uang diterima sesuai, ada kekurangan Rp 10.000..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check mr-1"></i>Verifikasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if(session('status'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.showToast) showToast('{{ session("status") }}', 'success');
});
</script>
@endif
@endsection
