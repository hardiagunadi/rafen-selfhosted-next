@extends('layouts.admin')

@section('title', 'Laporan Revenue')

@section('content')
    <div class="card mb-3">
        <div class="card-header">
            <h4 class="mb-0">Laporan Revenue Langganan</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('super-admin.reports.revenue') }}">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="start-date">Tanggal Mulai</label>
                        <input
                            id="start-date"
                            name="start_date"
                            type="date"
                            class="form-control"
                            value="{{ $startDate->toDateString() }}"
                        >
                    </div>
                    <div class="form-group col-md-4">
                        <label for="end-date">Tanggal Selesai</label>
                        <input
                            id="end-date"
                            name="end_date"
                            type="date"
                            class="form-control"
                            value="{{ $endDate->toDateString() }}"
                        >
                    </div>
                    <div class="form-group col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                    </div>
                </div>
            </form>

            <div class="alert alert-info mb-0">
                Total revenue periode <strong>{{ $startDate->format('d/m/Y') }}</strong> -
                <strong>{{ $endDate->format('d/m/Y') }}</strong>:
                <strong>Rp {{ number_format((float) $subscriptionRevenue, 0, ',', '.') }}</strong>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Revenue Harian</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive mb-0">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($dailyRevenue as $row)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($row->date)->format('d/m/Y') }}</td>
                                        <td class="text-right">Rp {{ number_format((float) $row->total, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center text-muted py-3">Belum ada data revenue harian.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Revenue per Paket</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive mb-0">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Paket</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($revenueByPlan as $row)
                                    <tr>
                                        <td>{{ $row->plan_name }}</td>
                                        <td class="text-right">Rp {{ number_format((float) $row->total, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center text-muted py-3">Belum ada data revenue per paket.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
