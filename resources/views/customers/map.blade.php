@extends('layouts.admin')

@section('title', 'Peta Pelanggan')

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
<style>
    #customer-map {
        height: 70vh;
        min-height: 420px;
        border-radius: 6px;
        border: 1px solid #dee2e6;
    }
</style>

<div class="row mb-3">
    <div class="col-md-3 col-sm-6 mb-2">
        <div class="p-3 border rounded h-100">
            <div class="small text-muted">Total ODP</div>
            <div class="h5 mb-0">{{ $summary['odps_total'] }}</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-2">
        <div class="p-3 border rounded h-100">
            <div class="small text-muted">ODP Bertitik</div>
            <div class="h5 mb-0">{{ $summary['odps_with_coordinate'] }}</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-2">
        <div class="p-3 border rounded h-100">
            <div class="small text-muted">Total Pelanggan PPP</div>
            <div class="h5 mb-0">{{ $summary['customers_total'] }}</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-2">
        <div class="p-3 border rounded h-100">
            <div class="small text-muted">Pelanggan Bertitik</div>
            <div class="h5 mb-0">{{ $summary['customers_with_coordinate'] }}</div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('customer-map.index') }}" class="form-row">
            <div class="form-group col-md-4 mb-2">
                <label class="small text-muted">Filter ODP</label>
                <select name="odp_id" class="form-control form-control-sm">
                    <option value="">- Semua ODP -</option>
                    @foreach($odps as $odp)
                        <option value="{{ $odp->id }}" @selected($selectedOdpId === $odp->id)>
                            {{ $odp->code }} - {{ $odp->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-3 mb-2">
                <label class="small text-muted">Status Akun</label>
                <select name="status_akun" class="form-control form-control-sm">
                    <option value="">- Semua -</option>
                    <option value="enable" @selected($selectedStatusAkun === 'enable')>Enable</option>
                    <option value="disable" @selected($selectedStatusAkun === 'disable')>Disable</option>
                    <option value="isolir" @selected($selectedStatusAkun === 'isolir')>Isolir</option>
                </select>
            </div>
            <div class="form-group col-md-5 mb-2 d-flex align-items-end justify-content-end">
                <button type="submit" class="btn btn-primary btn-sm mr-2">Terapkan Filter</button>
                <a href="{{ route('customer-map.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h4 class="mb-0">Peta ODP dan Pelanggan PPP</h4>
    </div>
    <div class="card-body">
        <div id="map-cache-status" class="small text-muted mb-2"></div>
        <div id="customer-map"></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    var odpMarkers = {{ Js::from($odpMarkers) }};
    var customerMarkers = {{ Js::from($customerMarkers) }};
    var mapCacheConfigEndpoint = @json($mapCacheConfigEndpoint);
    var mapCacheTilesEndpoint = @json($mapCacheTilesEndpoint);
    var mapInitialized = false;

    function customerColor(status) {
        if (status === 'enable') return '#28a745';
        if (status === 'isolir') return '#dc3545';
        return '#6c757d';
    }

    function odpColor(status) {
        if (status === 'active') return '#007bff';
        if (status === 'maintenance') return '#ffc107';
        return '#6c757d';
    }

    function initMap() {
        if (mapInitialized) return;

        var mapContainer = document.getElementById('customer-map');
        if (!mapContainer) return;

        mapInitialized = true;
        var map = L.map('customer-map', { preferCanvas: true }).setView([-7.36, 109.90], 11);
        var streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        var earthLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19,
            attribution: 'Tiles &copy; Esri'
        });

        L.control.layers({
            Street: streetLayer,
            Earth: earthLayer,
        }, null, { collapsed: true }).addTo(map);

        var allBounds = [];

        odpMarkers.forEach(function (odp) {
            var marker = L.circleMarker([odp.latitude, odp.longitude], {
                radius: 8,
                color: odpColor(odp.status),
                fillColor: odpColor(odp.status),
                fillOpacity: 0.9,
                weight: 2,
            }).addTo(map);

            marker.bindPopup(
                '<strong>' + odp.code + '</strong><br>'
                + (odp.name || '-') + '<br>'
                + 'Area: ' + (odp.area || '-') + '<br>'
                + 'Port: ' + odp.used_ports + ' / ' + odp.capacity_ports
            );

            allBounds.push([odp.latitude, odp.longitude]);
        });

        customerMarkers.forEach(function (customer) {
            var marker = L.circleMarker([customer.latitude, customer.longitude], {
                radius: 6,
                color: customerColor(customer.status_akun),
                fillColor: customerColor(customer.status_akun),
                fillOpacity: 0.7,
                weight: 1,
            }).addTo(map);

            marker.bindPopup(
                '<strong>' + (customer.name || '-') + '</strong><br>'
                + 'ID: ' + (customer.customer_id || '-') + '<br>'
                + 'Username: ' + (customer.username || '-') + '<br>'
                + 'ODP: ' + (customer.odp_code || '-') + '<br>'
                + 'Status: ' + (customer.status_akun || '-') + '<br>'
                + 'Akurasi: ' + (customer.accuracy ? customer.accuracy + ' m' : '-')
            );

            allBounds.push([customer.latitude, customer.longitude]);
        });

        if (allBounds.length > 0) {
            map.fitBounds(allBounds, { padding: [24, 24] });
        }

        initMapCache();
    }

    function setMapCacheStatus(message, type) {
        var statusElement = document.getElementById('map-cache-status');
        if (!statusElement) return;

        statusElement.textContent = message;
        statusElement.className = 'small mb-2';
        if (type === 'success') statusElement.classList.add('text-success');
        if (type === 'danger') statusElement.classList.add('text-danger');
        if (type === 'info') statusElement.classList.add('text-info');
        if (!type) statusElement.classList.add('text-muted');
    }

    async function waitForServiceWorkerController() {
        if (navigator.serviceWorker.controller) {
            return navigator.serviceWorker.controller;
        }

        return new Promise(function (resolve) {
            var resolved = false;
            var timeout = setTimeout(function () {
                if (!resolved) resolve(null);
            }, 4000);

            navigator.serviceWorker.addEventListener('controllerchange', function () {
                if (!resolved) {
                    resolved = true;
                    clearTimeout(timeout);
                    resolve(navigator.serviceWorker.controller || null);
                }
            });
        });
    }

    async function initMapCache() {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        try {
            const registration = await navigator.serviceWorker.register('/sw.js');
            await navigator.serviceWorker.ready;

            const configResponse = await fetch(mapCacheConfigEndpoint, {
                headers: { Accept: 'application/json' }
            });

            if (!configResponse.ok) {
                setMapCacheStatus('Cache peta tidak dapat dimuat.', 'danger');
                return;
            }

            const config = await configResponse.json();
            const worker = navigator.serviceWorker.controller
                || registration.active
                || registration.waiting
                || registration.installing
                || await waitForServiceWorkerController();

            if (!worker) {
                setMapCacheStatus('Service worker belum aktif, cache peta akan aktif di pemuatan berikutnya.', 'info');
                return;
            }

            if (config.auto_disabled) {
                worker.postMessage({
                    type: 'PURGE_TENANT_CACHE',
                    cachePrefix: config.tenant_cache_prefix,
                });
                setMapCacheStatus('Semua ODP sudah bertitik. Cache coverage otomatis dinonaktifkan dan dibersihkan.', 'success');
                return;
            }

            if (!config.enabled) {
                worker.postMessage({
                    type: 'PURGE_TENANT_CACHE',
                    cachePrefix: config.tenant_cache_prefix,
                });
                setMapCacheStatus('Cache coverage nonaktif. Aktifkan dari menu Pengaturan Tenant jika diperlukan.', null);
                return;
            }

            worker.postMessage({
                type: 'SET_ACTIVE_CACHE',
                cacheName: config.cache_name,
            });

            setMapCacheStatus('Sinkronisasi cache peta coverage berjalan...', 'info');
            const tilesResponse = await fetch(mapCacheTilesEndpoint, {
                headers: { Accept: 'application/json' }
            });

            if (!tilesResponse.ok) {
                setMapCacheStatus('Daftar tile coverage tidak dapat dimuat.', 'danger');
                return;
            }

            const tilesPayload = await tilesResponse.json();
            if (!tilesPayload.enabled || !Array.isArray(tilesPayload.urls) || tilesPayload.urls.length === 0) {
                setMapCacheStatus('Cache coverage aktif, tetapi belum ada tile yang dapat diproses.', 'info');
                return;
            }

            worker.postMessage({
                type: 'WARMUP_TILES',
                cacheName: config.cache_name,
                urls: tilesPayload.urls,
            });

            setMapCacheStatus('Cache coverage aktif. Tile peta disiapkan untuk mode sinyal lemah.', 'success');
        } catch (error) {
            setMapCacheStatus('Gagal inisialisasi cache peta pada browser ini.', 'danger');
        }
    }

    document.addEventListener('DOMContentLoaded', initMap);
    if (document.readyState !== 'loading') initMap();
})();
</script>
@endpush
