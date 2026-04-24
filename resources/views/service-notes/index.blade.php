@extends('layouts.admin')

@section('title', 'Riwayat Nota Layanan')

@section('content')
    <div class="alert alert-light border mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:.75rem;">
            <div>
                <h4 class="mb-1">Riwayat Nota Layanan</h4>
                <p class="mb-0 text-muted">Cari nota layanan, konfirmasi transfer yang sudah diterima, dan cetak ulang kapan saja.</p>
            </div>
            <div class="d-flex flex-wrap" style="gap:.75rem;">
                <div class="text-right">
                    <div class="small text-uppercase text-muted">Jumlah Nota</div>
                    <div class="h5 mb-0">{{ number_format($summary['count'] ?? 0) }}</div>
                </div>
                <div class="text-right">
                    <div class="small text-uppercase text-muted">Menunggu</div>
                    <div class="h5 mb-0">{{ number_format($summary['pending_count'] ?? 0) }}</div>
                </div>
                <div class="text-right">
                    <div class="small text-uppercase text-muted">Total Diterima</div>
                    <div class="h5 mb-0">Rp {{ number_format($summary['paid_total'] ?? 0, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('service-notes.index') }}">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="search">Cari Nota / Pelanggan</label>
                        <input type="text" class="form-control" id="search" name="search" value="{{ $filters['search'] }}" placeholder="Nomor nota, nama, atau ID pelanggan">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="note_type">Jenis Nota</label>
                        <select class="form-control" id="note_type" name="note_type">
                            <option value="">Semua</option>
                            <option value="aktivasi" @selected($filters['note_type'] === 'aktivasi')>Aktivasi</option>
                            <option value="pemasangan" @selected($filters['note_type'] === 'pemasangan')>Pemasangan</option>
                            <option value="perbaikan" @selected($filters['note_type'] === 'perbaikan')>Perbaikan</option>
                            <option value="lainnya" @selected($filters['note_type'] === 'lainnya')>Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="payment_method">Metode Bayar</label>
                        <select class="form-control" id="payment_method" name="payment_method">
                            <option value="">Semua</option>
                            <option value="cash" @selected($filters['payment_method'] === 'cash')>Cash</option>
                            <option value="transfer" @selected($filters['payment_method'] === 'transfer')>Transfer</option>
                            <option value="lainnya" @selected($filters['payment_method'] === 'lainnya')>Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="">Semua</option>
                            <option value="pending" @selected($filters['status'] === 'pending')>Menunggu</option>
                            <option value="paid" @selected($filters['status'] === 'paid')>Lunas</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="date_from">Tanggal Dari</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="{{ $filters['date_from'] }}">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="date_to">Tanggal Sampai</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="{{ $filters['date_to'] }}">
                    </div>
                </div>

                <div class="d-flex justify-content-end flex-wrap" style="gap:.5rem;">
                    <a href="{{ route('service-notes.index') }}" class="btn btn-outline-secondary">Reset</a>
                    <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Daftar Nota Tersimpan</h4>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Nomor Nota</th>
                            <th>Pelanggan</th>
                            <th>Jenis Nota</th>
                            <th>Status</th>
                            <th>Metode Bayar</th>
                            <th>Petugas</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($serviceNotes as $serviceNote)
                            <tr>
                                <td>{{ $serviceNote->note_date?->format('d-m-Y') ?? '-' }}</td>
                                <td>
                                    <strong>{{ $serviceNote->document_number }}</strong>
                                    <div class="small text-muted">{{ $serviceNote->document_title }}</div>
                                </td>
                                <td>
                                    <div>{{ $serviceNote->customer_name ?: '-' }}</div>
                                    <div class="small text-muted">{{ $serviceNote->customer_id ?: '-' }}</div>
                                </td>
                                <td><span class="badge badge-info text-uppercase">{{ $serviceNote->note_type }}</span></td>
                                <td>
                                    <span class="badge badge-{{ $serviceNote->statusBadgeClass() }}">{{ $serviceNote->statusLabel() }}</span>
                                </td>
                                <td><span class="badge badge-secondary text-uppercase">{{ $serviceNote->payment_method }}</span></td>
                                <td>{{ $serviceNote->paidBy?->name ?? $serviceNote->creator?->name ?? '-' }}</td>
                                <td class="text-right font-weight-bold">Rp {{ number_format((float) $serviceNote->total, 0, ',', '.') }}</td>
                                <td class="text-right">
                                    @if ($serviceNote->requiresTransferConfirmation())
                                        <a href="{{ route('service-notes.edit', $serviceNote) }}" class="btn btn-warning btn-sm" title="Edit nota belum lunas">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <form method="POST" action="{{ route('service-notes.confirm-transfer', $serviceNote) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm" title="Konfirmasi transfer diterima" onclick="return confirm('Tandai transfer untuk nota ini sudah diterima?')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('service-notes.print', $serviceNote) }}" target="_blank" class="btn btn-secondary btn-sm" title="Cetak ulang nota">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">Belum ada nota layanan tersimpan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($serviceNotes->hasPages())
            <div class="card-footer">
                {{ $serviceNotes->links() }}
            </div>
        @endif
    </div>
@endsection
