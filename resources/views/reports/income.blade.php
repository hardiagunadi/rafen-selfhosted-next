@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    @php
        $hotspotModuleEnabled = auth()->user()?->isHotspotModuleEnabled() ?? true;
        $isDailyReport = ($reportType ?? 'daily') === 'daily';
        $showBhpUsoInputs = ($reportType ?? 'daily') === 'bhp_uso';
        $canManageExpense = auth()->user()->isSuperAdmin() || in_array(auth()->user()->role, ['administrator', 'keuangan'], true);
        $summary = $report['summary'] ?? [];
    @endphp

    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">{{ $pageTitle }}</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('reports.income') }}" class="mb-4">
                <input type="hidden" name="report" value="{{ $reportType }}">

                <div class="form-group">
                    <label class="d-block">Tipe User</label>
                    @php $tipeUser = $filters['tipe_user'] ?? 'semua'; @endphp
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="tipe_user" id="tipe-semua" value="semua" @checked($tipeUser === 'semua')>
                        <label class="form-check-label" for="tipe-semua">SEMUA TIPE</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="tipe_user" id="tipe-customer" value="customer" @checked($tipeUser === 'customer')>
                        <label class="form-check-label" for="tipe-customer">CUSTOMER</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="tipe_user" id="tipe-voucher" value="voucher" @checked($tipeUser === 'voucher')>
                        <label class="form-check-label" for="tipe-voucher">VOUCHER</label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="service-type">Tipe Service</label>
                    <select class="form-control" id="service-type" name="service_type">
                        <option value="" @selected(($filters['service_type'] ?? '') === '')>- Semua Transaksi -</option>
                        <option value="pppoe" @selected(($filters['service_type'] ?? '') === 'pppoe')>PPPoE</option>
                        @if($hotspotModuleEnabled)
                            <option value="hotspot" @selected(($filters['service_type'] ?? '') === 'hotspot')>Hotspot</option>
                        @endif
                        <option value="voucher" @selected(($filters['service_type'] ?? '') === 'voucher')>Voucher</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="owner-filter">Owner Data</label>
                    <select class="form-control" id="owner-filter" name="owner_id">
                        @if(auth()->user()->isSuperAdmin())
                            <option value="" @selected(($filters['owner_id'] ?? '') === '')>- Semua Owner -</option>
                        @endif
                        @foreach($owners as $owner)
                            <option value="{{ $owner->id }}" @selected(($filters['owner_id'] ?? '') == $owner->id)>{{ $owner->name }}</option>
                        @endforeach
                    </select>
                </div>

                @if($isDailyReport)
                    <div class="form-group">
                        <label for="report-date">Tanggal Laporan</label>
                        <input type="date" class="form-control" id="report-date" name="date" value="{{ $filters['date'] ?? now()->toDateString() }}">
                    </div>
                @else
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="start-date">Tanggal Mulai</label>
                            <input type="date" class="form-control" id="start-date" name="start_date" value="{{ $filters['start_date'] ?? now()->startOfMonth()->toDateString() }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="end-date">Tanggal Selesai</label>
                            <input type="date" class="form-control" id="end-date" name="end_date" value="{{ $filters['end_date'] ?? now()->endOfMonth()->toDateString() }}">
                        </div>
                    </div>
                @endif

                @if($showBhpUsoInputs)
                    <div class="border rounded p-3 mb-3">
                        <h6 class="mb-3">Parameter BHP | USO</h6>
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label for="bhp-rate">Tarif BHP (%)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="bhp-rate" name="bhp_rate" value="{{ $filters['bhp_rate'] ?? 0.5 }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="uso-rate">Tarif USO (%)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="uso-rate" name="uso_rate" value="{{ $filters['uso_rate'] ?? 1.25 }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="bad-debt-deduction">Potongan Piutang Tak Tertagih</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="bad-debt-deduction" name="bad_debt_deduction" value="{{ $filters['bad_debt_deduction'] ?? 0 }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="interconnection-deduction">Potongan Beban Interkoneksi</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="interconnection-deduction" name="interconnection_deduction" value="{{ $filters['interconnection_deduction'] ?? 0 }}">
                            </div>
                        </div>
                    </div>
                @endif

                <div class="text-right">
                    <button type="submit" class="btn btn-primary">Lihat Laporan</button>
                </div>
            </form>

            <div class="alert alert-light border">
                Periode laporan: <strong>{{ $report['period']['label'] ?? '-' }}</strong>
            </div>

            @if(in_array($reportType, ['daily', 'period'], true))
                <div class="alert alert-info">
                    Total Pendapatan:
                    <strong>Rp {{ number_format($summary['total_income'] ?? 0, 0, ',', '.') }}</strong>
                    | Customer:
                    <strong>Rp {{ number_format($summary['customer_income'] ?? 0, 0, ',', '.') }}</strong>
                    | Voucher:
                    <strong>Rp {{ number_format($summary['voucher_income'] ?? 0, 0, ',', '.') }}</strong>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Referensi</th>
                                <th>Tipe User</th>
                                <th>Service</th>
                                <th>Owner</th>
                                <th>Jumlah (IDR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($report['items'] as $item)
                                <tr>
                                    <td>{{ $item['time'] }}</td>
                                    <td>{{ $item['reference'] ?? '-' }}</td>
                                    <td>{{ strtoupper($item['user_type']) }}</td>
                                    <td>{{ strtoupper($item['service']) }}</td>
                                    <td>{{ $item['owner'] }}</td>
                                    <td class="text-right">{{ number_format($item['amount'], 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Belum ada transaksi untuk filter ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @elseif($reportType === 'expense')
                @if($canManageExpense)
                    <div class="card card-outline card-primary mb-3">
                        <div class="card-header py-2">
                            <h6 class="mb-0">Tambah Pengeluaran Manual</h6>
                        </div>
                        <div class="card-body">
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0 pl-3">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            <form method="POST" action="{{ route('reports.expenses.store') }}">
                                @csrf
                                <div class="form-row">
                                    @if(auth()->user()->isSuperAdmin())
                                        <div class="form-group col-md-3">
                                            <label for="expense-owner-id">Owner</label>
                                            <select class="form-control" id="expense-owner-id" name="owner_id" required>
                                                <option value="">- Pilih Owner -</option>
                                                @foreach($owners as $owner)
                                                    <option value="{{ $owner->id }}" @selected((string) old('owner_id', (string) ($filters['owner_id'] ?? '')) === (string) $owner->id)>{{ $owner->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                    <div class="form-group col-md-{{ auth()->user()->isSuperAdmin() ? '3' : '4' }}">
                                        <label for="expense-date">Tanggal</label>
                                        <input type="date" class="form-control" id="expense-date" name="expense_date" value="{{ old('expense_date', now()->toDateString()) }}" required>
                                    </div>
                                    <div class="form-group col-md-{{ auth()->user()->isSuperAdmin() ? '3' : '4' }}">
                                        <label for="expense-category">Kategori</label>
                                        <input type="text" class="form-control" id="expense-category" name="category" value="{{ old('category') }}" placeholder="Contoh: Gaji Teknisi" required>
                                    </div>
                                    <div class="form-group col-md-{{ auth()->user()->isSuperAdmin() ? '3' : '4' }}">
                                        <label for="expense-amount">Nominal (IDR)</label>
                                        <input type="number" class="form-control" id="expense-amount" name="amount" step="0.01" min="1" value="{{ old('amount') }}" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label for="expense-service">Alokasi Service</label>
                                        <select class="form-control" id="expense-service" name="service_type" required>
                                            <option value="general" @selected(old('service_type', 'general') === 'general')>General</option>
                                            <option value="pppoe" @selected(old('service_type') === 'pppoe')>PPPoE</option>
                                            @if($hotspotModuleEnabled)
                                                <option value="hotspot" @selected(old('service_type') === 'hotspot')>Hotspot</option>
                                            @endif
                                            <option value="voucher" @selected(old('service_type') === 'voucher')>Voucher</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="expense-payment-method">Metode Pembayaran</label>
                                        <input type="text" class="form-control" id="expense-payment-method" name="payment_method" value="{{ old('payment_method') }}" placeholder="Contoh: transfer / cash">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="expense-reference">Referensi</label>
                                        <input type="text" class="form-control" id="expense-reference" name="reference" value="{{ old('reference') }}" placeholder="Nomor nota / bukti">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="expense-description">Keterangan</label>
                                    <textarea class="form-control" id="expense-description" name="description" rows="2" placeholder="Catatan tambahan">{{ old('description') }}</textarea>
                                </div>
                                <div class="text-right">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus-circle mr-1"></i>Simpan Pengeluaran
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                <div class="alert alert-warning">
                    Total Pengeluaran:
                    <strong>Rp {{ number_format($summary['total_expense'] ?? 0, 0, ',', '.') }}</strong>
                    | Biaya Gateway:
                    <strong>Rp {{ number_format($summary['gateway_expense'] ?? 0, 0, ',', '.') }}</strong>
                    | Pengeluaran Manual:
                    <strong>Rp {{ number_format($summary['manual_expense'] ?? 0, 0, ',', '.') }}</strong>
                    | Estimasi BHP:
                    <strong>Rp {{ number_format($summary['bhp_amount'] ?? 0, 0, ',', '.') }}</strong>
                    | Estimasi USO:
                    <strong>Rp {{ number_format($summary['uso_amount'] ?? 0, 0, ',', '.') }}</strong>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Kategori</th>
                                <th>Keterangan</th>
                                <th>Owner</th>
                                <th>Jumlah (IDR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($report['items'] as $item)
                                <tr>
                                    <td>{{ $item['time'] }}</td>
                                    <td>{{ $item['category'] }}</td>
                                    <td>{{ $item['description'] }}</td>
                                    <td>{{ $item['owner'] }}</td>
                                    <td class="text-right">{{ number_format($item['amount'], 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Belum ada data pengeluaran pada periode ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @elseif($reportType === 'profit_loss')
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Akun</th>
                                <th class="text-right">Jumlah (IDR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Pendapatan Kotor</td>
                                <td class="text-right">{{ number_format($summary['gross_revenue'] ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td>Potongan Piutang Tak Tertagih</td>
                                <td class="text-right">({{ number_format($summary['bad_debt_deduction'] ?? 0, 0, ',', '.') }})</td>
                            </tr>
                            <tr>
                                <td>Potongan Beban Interkoneksi</td>
                                <td class="text-right">({{ number_format($summary['interconnection_deduction'] ?? 0, 0, ',', '.') }})</td>
                            </tr>
                            <tr>
                                <td>Dasar Pengenaan BHP | USO</td>
                                <td class="text-right">{{ number_format($summary['revenue_basis'] ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td>Beban Operasional (Gateway)</td>
                                <td class="text-right">({{ number_format($summary['gateway_expense'] ?? 0, 0, ',', '.') }})</td>
                            </tr>
                            <tr>
                                <td>Beban Operasional (Manual)</td>
                                <td class="text-right">({{ number_format($summary['manual_expense'] ?? 0, 0, ',', '.') }})</td>
                            </tr>
                            <tr>
                                <td>Beban BHP</td>
                                <td class="text-right">({{ number_format($summary['bhp_amount'] ?? 0, 0, ',', '.') }})</td>
                            </tr>
                            <tr>
                                <td>Beban USO</td>
                                <td class="text-right">({{ number_format($summary['uso_amount'] ?? 0, 0, ',', '.') }})</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="{{ ($summary['net_profit'] ?? 0) >= 0 ? 'table-success' : 'table-danger' }}">
                                <th>Laba Bersih</th>
                                <th class="text-right">{{ number_format($summary['net_profit'] ?? 0, 0, ',', '.') }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @elseif($reportType === 'bhp_uso')
                <div class="table-responsive mb-3">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th style="width: 45%;">Pendapatan Kotor</th>
                                <td class="text-right">Rp {{ number_format($summary['gross_revenue'] ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <th>Potongan Piutang Tak Tertagih</th>
                                <td class="text-right">Rp {{ number_format($summary['bad_debt_deduction'] ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <th>Potongan Beban Interkoneksi</th>
                                <td class="text-right">Rp {{ number_format($summary['interconnection_deduction'] ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <th>Total Potongan Diakui</th>
                                <td class="text-right">Rp {{ number_format($summary['deduction_total'] ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            <tr class="table-light">
                                <th>Dasar Pengenaan BHP | USO</th>
                                <td class="text-right">Rp {{ number_format($summary['revenue_basis'] ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <th>BHP ({{ rtrim(rtrim(number_format($summary['bhp_rate'] ?? 0, 2, ',', '.'), '0'), ',') }}%)</th>
                                <td class="text-right">Rp {{ number_format($summary['bhp_amount'] ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <th>USO ({{ rtrim(rtrim(number_format($summary['uso_rate'] ?? 0, 2, ',', '.'), '0'), ',') }}%)</th>
                                <td class="text-right">Rp {{ number_format($summary['uso_amount'] ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            <tr class="table-warning">
                                <th>Total Kewajiban BHP | USO</th>
                                <td class="text-right"><strong>Rp {{ number_format($summary['total_obligation'] ?? 0, 0, ',', '.') }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-secondary mb-0">
                    Acuan regulasi:
                    <ul class="mb-0 mt-2 pl-3">
                        <li>BHP: {{ $bhpUsoReference['bhp'] ?? '-' }}</li>
                        <li>USO: {{ $bhpUsoReference['uso'] ?? '-' }}</li>
                        <li>Potongan pendapatan kotor: {{ $bhpUsoReference['deduction'] ?? '-' }}</li>
                    </ul>
                </div>
            @endif
        </div>
    </div>
@endsection
