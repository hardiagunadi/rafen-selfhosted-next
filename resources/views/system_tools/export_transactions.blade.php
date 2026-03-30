@extends('layouts.admin')

@section('title', 'Ekspor Transaksi')

@section('content')
<div class="card">
    <div class="card-header">
        <h4 class="mb-0">Ekspor Transaksi / Invoice ke CSV / Excel</h4>
    </div>
    <div class="card-body">
        <form action="{{ route('tools.export-transactions.download') }}" method="GET">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Tanggal Dari</label>
                        <input type="date" name="date_from" class="form-control" value="{{ request('date_from', date('Y-m-01')) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Tanggal Sampai</label>
                        <input type="date" name="date_to" class="form-control" value="{{ request('date_to', date('Y-m-d')) }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Status Pembayaran</label>
                        <select name="status" class="form-control">
                            <option value="" {{ request('status') === null || request('status') === '' ? 'selected' : '' }}>Semua Status</option>
                            <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Lunas</option>
                            <option value="unpaid" {{ request('status') === 'unpaid' ? 'selected' : '' }}>Belum Lunas</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Format File</label>
                        <select name="format" class="form-control">
                            <option value="csv" {{ request('format', 'csv') === 'csv' ? 'selected' : '' }}>CSV</option>
                            <option value="excel" {{ request('format') === 'excel' ? 'selected' : '' }}>Excel (.xls)</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-group w-100">
                        <button type="submit" class="btn btn-success btn-block">
                            <i class="fas fa-download mr-1"></i>Download File
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <hr>
        <div class="alert alert-info mb-0">
            <i class="fas fa-info-circle mr-1"></i>
            File berisi: nomor invoice, nama pelanggan, paket, nominal, status, tanggal jatuh tempo, tanggal bayar, dan metode pembayaran (CSV atau Excel).
        </div>
    </div>
</div>
@endsection
