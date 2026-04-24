@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    @php
        $hotspotModuleEnabled = $hotspotModuleEnabled ?? true;
        $hotspotOnline = $hotspotModuleEnabled ? $stats['hotspot_online'] : 0;
        $totalActiveSessions = $stats['ppp_online'] + $hotspotOnline;
        $routerOnlinePercent = $stats['router_total'] > 0 ? (int) round(($stats['router_online'] / $stats['router_total']) * 100) : 0;
        $routerOfflinePercent = $stats['router_total'] > 0 ? (int) round(($stats['router_offline'] / $stats['router_total']) * 100) : 0;
        $activeSessionPercent = $stats['ppp_users'] > 0 ? (int) round(($totalActiveSessions / $stats['ppp_users']) * 100) : 0;
        $activeSessionPercent = max(0, min(100, $activeSessionPercent));
        $pppMixPercent = $totalActiveSessions > 0 ? (int) round(($stats['ppp_online'] / $totalActiveSessions) * 100) : 100;
        $hotspotMixPercent = $hotspotModuleEnabled && $totalActiveSessions > 0 ? (int) round(($hotspotOnline / $totalActiveSessions) * 100) : 0;
        $invoiceStateClass = $stats['invoice_count'] > 0 ? 'badge-warning' : 'badge-success';
        $invoiceStateLabel = $stats['invoice_count'] > 0 ? 'Perlu tindak lanjut' : 'Tagihan aman';
        $hideHeroCard = auth()->user()->role === 'teknisi';
    @endphp

    <style>
        .dashboard-shell {
            --dash-bg: #f5f7fb;
            --dash-surface: #ffffff;
            --dash-border: #dce5f1;
            --dash-text: #111827;
            --dash-text-soft: #5b6b83;
            --dash-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
            --dash-shadow-soft: 0 8px 16px rgba(15, 23, 42, 0.06);
            position: relative;
            border-radius: 20px;
            padding: 4px;
            animation: dashboard-rise 260ms ease-out;
        }

        .dashboard-shell::before {
            content: '';
            position: absolute;
            inset: 0;
            z-index: 0;
            border-radius: 20px;
            background:
                radial-gradient(circle at 12% 10%, rgba(14, 116, 144, 0.12), transparent 34%),
                radial-gradient(circle at 90% 0%, rgba(20, 184, 166, 0.09), transparent 28%),
                var(--dash-bg);
        }

        .dashboard-shell > * {
            position: relative;
            z-index: 1;
        }

        .dashboard-hero {
            border-radius: 18px;
            border: 1px solid var(--dash-border);
            box-shadow: var(--dash-shadow-soft);
            overflow: hidden;
            background:
                radial-gradient(circle at top right, rgba(14, 116, 144, 0.12), transparent 42%),
                linear-gradient(155deg, #f9fcff 0%, #f2f8ff 42%, #eef6ff 100%);
        }

        .hero-kicker {
            margin: 0;
            color: #0f766e;
            font-size: 0.73rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .hero-title {
            margin: 10px 0 0;
            color: var(--dash-text);
            font-size: 1.65rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .hero-subtitle {
            margin: 10px 0 0;
            max-width: 700px;
            color: var(--dash-text-soft);
            line-height: 1.58;
        }

        .hero-inline-grid {
            margin-top: 18px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
        }

        .hero-inline-item {
            border: 1px solid #d9e5f2;
            background: rgba(255, 255, 255, 0.82);
            border-radius: 10px;
            padding: 10px 12px;
        }

        .hero-inline-label {
            display: block;
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .hero-inline-value {
            display: block;
            margin-top: 4px;
            color: #0f172a;
            font-size: 1rem;
            font-weight: 700;
        }

        .hero-clock-panel {
            border: 1px solid #d8e4f2;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.84);
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.04);
            padding: 14px;
        }

        .hero-clock-label {
            margin: 0;
            color: #64748b;
            font-size: 0.73rem;
            font-weight: 700;
            letter-spacing: 0.07em;
            text-transform: uppercase;
        }

        .hero-clock {
            margin-top: 4px;
            color: #0f172a;
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1;
        }

        .hero-date {
            margin-top: 6px;
            color: #475569;
            font-size: 0.9rem;
        }

        .hero-system-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            margin-top: 12px;
        }

        .hero-system-item {
            border: 1px solid #e2eaf4;
            border-radius: 9px;
            background: #f8fbff;
            padding: 8px;
        }

        .hero-system-label {
            display: block;
            color: #6b7280;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .hero-system-value {
            display: block;
            margin-top: 3px;
            color: #0f172a;
            font-size: 0.87rem;
            font-weight: 700;
        }

        .stat-card {
            height: 100%;
            border-radius: 14px;
            border: 1px solid var(--dash-border);
            background: var(--dash-surface);
            box-shadow: var(--dash-shadow-soft);
            overflow: hidden;
            transition: transform 180ms ease, box-shadow 180ms ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--dash-shadow);
        }

        .stat-card-body {
            padding: 1rem;
        }

        .stat-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }

        .stat-title {
            margin: 0;
            color: #64748b;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .stat-value {
            margin: 6px 0 0;
            color: #111827;
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .stat-sub {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 0.86rem;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            color: #fff;
            font-size: 0.95rem;
            flex-shrink: 0;
        }

        .stat-icon-income { background: linear-gradient(140deg, #0f766e, #14b8a6); }
        .stat-icon-invoice { background: linear-gradient(140deg, #b45309, #f59e0b); }
        .stat-icon-ppp { background: linear-gradient(140deg, #0369a1, #0ea5e9); }
        .stat-icon-hotspot { background: linear-gradient(140deg, #9d174d, #ec4899); }

        .stat-trail {
            height: 6px;
            border-radius: 999px;
            overflow: hidden;
            background: #e6edf7;
            margin-top: 12px;
        }

        .stat-trail > span {
            display: block;
            height: 100%;
            border-radius: inherit;
        }

        .trail-income { background: linear-gradient(90deg, #0f766e, #14b8a6); width: 100%; }
        .trail-invoice { background: linear-gradient(90deg, #b45309, #f59e0b); width: {{ min(100, $stats['invoice_count'] * 6) }}%; }
        .trail-ppp { background: linear-gradient(90deg, #0369a1, #0ea5e9); width: {{ $pppMixPercent }}%; }
        .trail-hotspot { background: linear-gradient(90deg, #9d174d, #ec4899); width: {{ $hotspotMixPercent }}%; }

        .stat-link {
            display: block;
            border-top: 1px solid #edf2f7;
            padding: 9px 12px;
            color: #334155;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
        }

        .stat-link:hover {
            text-decoration: none;
            color: #0f172a;
            background: #f8fafc;
        }

        .dashboard-panel {
            height: 100%;
            border-radius: 14px;
            border: 1px solid var(--dash-border);
            box-shadow: var(--dash-shadow-soft);
            background: var(--dash-surface);
        }

        .dashboard-panel .card-header {
            background: transparent;
            border: 0;
            padding: 1rem 1rem 0.5rem;
        }

        .panel-title {
            margin: 0;
            color: var(--dash-text);
            font-size: 1.02rem;
            font-weight: 700;
        }

        .panel-subtitle {
            margin: 4px 0 0;
            color: var(--dash-text-soft);
            font-size: 0.86rem;
        }

        .health-item {
            border: 1px solid #e4ecf6;
            border-radius: 12px;
            padding: 12px;
            background: #fcfdff;
            margin-bottom: 12px;
        }

        .health-item:last-child {
            margin-bottom: 0;
        }

        .health-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
            color: #334155;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .health-chip {
            font-size: 0.77rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 999px;
            background: #eef2ff;
            color: #334155;
        }

        .quick-links-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-top: 12px;
        }

        .quick-links-grid .btn {
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .income-modal .modal-content {
            border-radius: 14px;
            border: 1px solid #dbe4f1;
            box-shadow: var(--dash-shadow);
        }

        @media (max-width: 767.98px) {
            .hero-system-grid {
                grid-template-columns: 1fr;
            }

            .quick-links-grid {
                grid-template-columns: 1fr;
            }
        }

        @keyframes dashboard-rise {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>

    <div class="dashboard-shell">
        @if(! $hideHeroCard)
            <div class="card dashboard-hero mb-4">
                <div class="card-body p-4 p-md-5">
                    <div class="row align-items-end">
                        <div class="col-lg-8">
                            <p class="hero-kicker">Ringkasan Operasional</p>
                            <h2 class="hero-title">Dashboard Operasional ISP</h2>
                            <p class="hero-subtitle">
                                Monitor kesehatan jaringan, sesi aktif pelanggan, dan performa billing harian dalam layout yang lebih cepat dibaca.
                            </p>

                            <div class="hero-inline-grid">
                                <div class="hero-inline-item">
                                    <span class="hero-inline-label">Health Router</span>
                                    <span class="hero-inline-value">{{ $routerOnlinePercent }}%</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 mt-4 mt-lg-0">
                            <div class="hero-clock-panel">
                                <p class="hero-clock-label">Waktu Server</p>
                                <div class="hero-clock" id="dashboard-live-clock">{{ now()->format('H:i:s') }}</div>
                                <div class="hero-date">{{ now()->format('d M Y') }}</div>

                                <div class="hero-system-grid">
                                    <div class="hero-system-item">
                                        <span class="hero-system-label">Akun Enable <span style="font-size:.7em;opacity:.7">(PPPoE)</span></span>
                                        <span class="hero-system-value">{{ number_format($stats['ppp_active']) }}</span>
                                    </div>
                                    <div class="hero-system-item">
                                        <span class="hero-system-label">Pelanggan Isolir</span>
                                        <span class="hero-system-value">{{ number_format($stats['ppp_isolir']) }}</span>
                                    </div>
                                    <div class="hero-system-item">
                                        <span class="hero-system-label">Invoice Lunas Bln Ini</span>
                                        <span class="hero-system-value">{{ number_format($stats['invoice_paid_month']) }} / {{ number_format($stats['invoice_total_month']) }}</span>
                                    </div>
                                    <div class="hero-system-item">
                                        <span class="hero-system-label">
                                            Sedang Online
                                            @if($hotspotModuleEnabled)
                                                <span style="font-size:.7em;opacity:.7">(PPPoE+HS)</span>
                                            @else
                                                <span style="font-size:.7em;opacity:.7">(PPPoE)</span>
                                            @endif
                                        </span>
                                        <span class="hero-system-value">{{ number_format($totalActiveSessions) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="row">
            <div class="col-lg-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <div class="stat-card-body">
                        <div class="stat-top">
                            <div>
                                <p class="stat-title">Income Hari Ini</p>
                                <p class="stat-value">Rp {{ number_format($stats['income_today'], 0, ',', '.') }}</p>
                                <p class="stat-sub">Pemasukan harian tenant</p>
                            </div>
                            <div class="stat-icon stat-icon-income">
                                <i class="fas fa-coins"></i>
                            </div>
                        </div>
                        <div class="stat-trail"><span class="trail-income"></span></div>
                    </div>
                    <button type="button" class="stat-link btn btn-link text-left" data-toggle="modal" data-target="#incomeModal">
                        Lihat detail pendapatan <i class="fas fa-arrow-right ml-1"></i>
                    </button>
                </div>
            </div>

            <div class="col-lg-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <div class="stat-card-body">
                        <div class="stat-top">
                            <div>
                                <p class="stat-title">Invoice Belum Lunas</p>
                                <p class="stat-value">{{ $stats['invoice_count'] }}</p>
                                <p class="stat-sub">Perpanjangan berjalan dan invoice tunggakan</p>
                            </div>
                            <div class="stat-icon stat-icon-invoice">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                        </div>
                        <span class="badge {{ $invoiceStateClass }}">{{ $invoiceStateLabel }}</span>
                        <div class="stat-trail"><span class="trail-invoice"></span></div>
                    </div>
                    <a href="{{ route('invoices.unpaid') }}" class="stat-link">
                        Buka daftar invoice <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <div class="stat-card-body">
                        <div class="stat-top">
                            <div>
                                <p class="stat-title">PPP Online</p>
                                <p class="stat-value" id="stat-ppp-online">{{ $stats['ppp_online'] }}</p>
                                <p class="stat-sub">{{ $hotspotModuleEnabled ? $pppMixPercent.'% dari total sesi aktif' : 'Sesi PPP aktif saat ini' }}</p>
                            </div>
                            <div class="stat-icon stat-icon-ppp">
                                <i class="fas fa-network-wired"></i>
                            </div>
                        </div>
                        <div class="stat-trail"><span class="trail-ppp"></span></div>
                    </div>
                    <a href="{{ route('sessions.pppoe') }}" class="stat-link">
                        Buka sesi PPP <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>

            @if($hotspotModuleEnabled)
                <div class="col-lg-3 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-card-body">
                            <div class="stat-top">
                                <div>
                                    <p class="stat-title">Hotspot Online</p>
                                    <p class="stat-value" id="stat-hotspot-online">{{ $stats['hotspot_online'] }}</p>
                                    <p class="stat-sub">{{ $hotspotMixPercent }}% dari total sesi aktif</p>
                                </div>
                                <div class="stat-icon stat-icon-hotspot">
                                    <i class="fas fa-wifi"></i>
                                </div>
                            </div>
                            <div class="stat-trail"><span class="trail-hotspot"></span></div>
                        </div>
                        <a href="{{ route('sessions.hotspot') }}" class="stat-link">
                            Buka sesi hotspot <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <div class="row">
            <div class="col-12 mb-3">
                <div class="card dashboard-panel">
                    <div class="card-header">
                        <h5 class="panel-title">Performa Jaringan</h5>
                        <p class="panel-subtitle">Progress operasional real-time untuk router, sesi, dan basis pelanggan.</p>
                    </div>
                    <div class="card-body pt-2">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="health-item">
                                    <div class="health-header">
                                        <span>Router Online</span>
                                        <span class="health-chip">{{ $stats['router_online'] }} / {{ $stats['router_total'] }}</span>
                                    </div>
                                    <div class="progress progress-sm">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $routerOnlinePercent }}%" aria-valuenow="{{ $routerOnlinePercent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>

                                <div class="health-item">
                                    <div class="health-header">
                                        <span>Router Offline</span>
                                        <span class="health-chip">{{ $stats['router_offline'] }} / {{ $stats['router_total'] }}</span>
                                    </div>
                                    <div class="progress progress-sm">
                                        <div class="progress-bar bg-danger" role="progressbar" style="width: {{ $routerOfflinePercent }}%" aria-valuenow="{{ $routerOfflinePercent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>

                                <div class="health-item">
                                    <div class="health-header">
                                        <span>Kepadatan Sesi Aktif</span>
                                        <span class="health-chip">{{ $totalActiveSessions }} / {{ $stats['ppp_users'] }}</span>
                                    </div>
                                    <div class="progress progress-sm">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: {{ $activeSessionPercent }}%" aria-valuenow="{{ $activeSessionPercent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="health-item">
                                    <div class="health-header mb-0">
                                        <span>Total Pelanggan PPP</span>
                                        <strong>{{ $stats['ppp_users'] }}</strong>
                                    </div>
                                </div>

                                <div class="health-item">
                                    <div class="health-header mb-0">
                                        <span>Total Sesi Aktif</span>
                                        <strong>{{ $totalActiveSessions }}</strong>
                                    </div>
                                </div>

                                <div class="health-item">
                                    <div class="health-header mb-0">
                                        <span>{{ $hotspotModuleEnabled ? 'Komposisi PPP / Hotspot' : 'Komposisi Sesi PPP' }}</span>
                                        <strong>{{ $hotspotModuleEnabled ? $stats['ppp_online'].' / '.$hotspotOnline : $stats['ppp_online'] }}</strong>
                                    </div>
                                </div>

                                <div class="quick-links-grid">
                                    <a class="btn btn-outline-primary btn-sm" href="{{ route('ppp-users.index') }}">List Pelanggan</a>
                                    <a class="btn btn-outline-info btn-sm" href="{{ route('odps.index') }}">Data ODP</a>
                                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('customer-map.index') }}">Peta Pelanggan</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade income-modal" id="incomeModal" tabindex="-1" aria-labelledby="incomeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="incomeModalLabel">Pendapatan Harian</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="GET" action="{{ route('reports.income') }}">
                        <div class="form-group">
                            <label class="d-block">Tipe User</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="tipe_user" id="user-semua" value="semua" checked>
                                <label class="form-check-label" for="user-semua">SEMUA TIPE</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="tipe_user" id="user-customer" value="customer">
                                <label class="form-check-label" for="user-customer">CUSTOMER</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="tipe_user" id="user-voucher" value="voucher">
                                <label class="form-check-label" for="user-voucher">VOUCHER</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="service-type">Tipe Service</label>
                            <select class="form-control" id="service-type" name="service_type">
                                <option value="">- Semua Transaksi -</option>
                                <option value="pppoe">PPPoE</option>
                                @if($hotspotModuleEnabled)
                                    <option value="hotspot">Hotspot</option>
                                @endif
                                <option value="voucher">Voucher</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="owner-filter">Owner Data</label>
                            <select class="form-control" id="owner-filter" name="owner_id">
                                <option value="">- Semua Owner -</option>
                                @foreach($owners as $owner)
                                    <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="text-right mt-4">
                            <button type="submit" class="btn btn-primary">Lihat Laporan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            function initClock() {
                var clock = document.getElementById('dashboard-live-clock');
                if (!clock) {
                    return;
                }

                setInterval(function () {
                    var now = new Date();
                    var hh = String(now.getHours()).padStart(2, '0');
                    var mm = String(now.getMinutes()).padStart(2, '0');
                    var ss = String(now.getSeconds()).padStart(2, '0');
                    clock.textContent = hh + ':' + mm + ':' + ss;
                }, 1000);
            }

            document.addEventListener('DOMContentLoaded', function () {
                initClock();
            });
        })();
    </script>
@endsection
