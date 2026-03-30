@extends('layouts.admin')

@section('title', 'Edit ODP')

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
<style>
.mf-page { display:flex;flex-direction:column;gap:1.1rem; }
.mf-page-header { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem; }
.mf-page-header-left { display:flex;align-items:center;gap:.85rem; }
.mf-page-icon { width:46px;height:46px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;color:#fff;flex-shrink:0;box-shadow:0 4px 14px rgba(0,0,0,.15); }
.mf-page-title { font-size:1.15rem;font-weight:700;color:var(--app-text,#0f172a);line-height:1.2; }
.mf-dim { color:var(--app-text-soft,#5b6b83);font-weight:500; }
.mf-page-sub { font-size:.8rem;color:var(--app-text-soft,#5b6b83);margin-top:.15rem; }
.mf-header-actions { display:flex;gap:.5rem;align-items:center;flex-wrap:wrap; }
.mf-btn-back { display:inline-flex;align-items:center;padding:.4rem .95rem;border-radius:9px;border:1px solid var(--app-border,#d7e1ee);background:#fff;color:var(--app-text,#0f172a);font-size:.82rem;font-weight:600;text-decoration:none;transition:background 140ms,transform 140ms; }
.mf-btn-back:hover { background:#f1f5ff;transform:translateY(-1px);color:var(--app-text,#0f172a);text-decoration:none; }
.mf-alert { display:flex;align-items:flex-start;gap:.5rem;padding:.85rem 1rem;border-radius:12px;font-size:.84rem; }
.mf-alert-danger { background:#fef2f2;border:1px solid #fecaca;color:#991b1b; }
.mf-grid { display:flex;flex-direction:column;gap:1rem; }
.mf-section { background:var(--app-surface,#fff);border:1px solid var(--app-border,#d7e1ee);border-radius:16px;box-shadow:0 4px 16px rgba(15,23,42,.05);overflow:hidden; }
.mf-section-header { display:flex;align-items:center;gap:.75rem;padding:.8rem 1.25rem;background:#f8fbff;border-bottom:1px solid var(--app-border,#d7e1ee); }
.mf-section-icon { width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.9rem;color:#fff;flex-shrink:0; }
.mf-section-title { font-size:.9rem;font-weight:700;color:var(--app-text,#0f172a); }
.mf-section-body { padding:1.1rem 1.25rem;display:flex;flex-direction:column;gap:.85rem; }
.mf-row { display:grid;grid-template-columns:repeat(2,1fr);gap:.85rem; }
.mf-row-3 { grid-template-columns:repeat(3,1fr); }
@media (max-width:767px) { .mf-row,.mf-row-3 { grid-template-columns:1fr; } }
.mf-field { display:flex;flex-direction:column;gap:.3rem; }
.mf-label { font-size:.77rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--app-text-soft,#5b6b83);display:flex;align-items:center;gap:.4rem; }
.mf-req { color:#ef4444;font-size:.85em; }
.mf-opt { font-size:.7rem;font-weight:600;border-radius:20px;padding:.1rem .45rem;background:rgba(100,116,139,.1);color:#64748b;text-transform:none;letter-spacing:0; }
.mf-hint { font-size:.73rem;color:var(--app-text-soft,#5b6b83); }
.mf-input { height:38px;border-radius:9px;border:1px solid var(--app-border,#d7e1ee);padding:0 .75rem;font-size:.85rem;color:var(--app-text,#0f172a);background:#fff;outline:none;width:100%;transition:border-color 150ms,box-shadow 150ms; }
select.mf-input { appearance:auto; }
textarea.mf-input { height:auto;padding:.5rem .75rem;resize:vertical; }
.mf-input:focus { border-color:#8fb5df;box-shadow:0 0 0 3px rgba(19,103,164,.12); }
.mf-input-error { border-color:#f43f5e !important; }
.mf-feedback { font-size:.76rem;color:#dc2626;margin-top:.1rem; }
.mf-footer { display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;padding:1rem 1.25rem;background:var(--app-surface,#fff);border:1px solid var(--app-border,#d7e1ee);border-radius:14px; }
.mf-btn-cancel { font-size:.84rem;font-weight:600;color:var(--app-text-soft,#5b6b83);text-decoration:none;padding:.4rem .75rem;transition:color 140ms; }
.mf-btn-cancel:hover { color:var(--app-text,#0f172a);text-decoration:none; }
.mf-btn-submit { display:inline-flex;align-items:center;height:38px;padding:0 1.4rem;border-radius:10px;border:none;background:linear-gradient(140deg,#0369a1,#0ea5e9);color:#fff;font-size:.88rem;font-weight:700;cursor:pointer;box-shadow:0 3px 12px rgba(14,165,233,.25);transition:opacity 140ms,transform 140ms; }
.mf-btn-submit:hover { opacity:.9;transform:translateY(-1px); }
#odp-location-map { height:320px;border:1px solid var(--app-border,#d7e1ee);border-radius:10px; }
</style>

<form action="{{ route('odps.update', $odp) }}" method="POST">
@csrf
@method('PUT')
<div class="mf-page">

    {{-- Page header --}}
    <div class="mf-page-header">
        <div class="mf-page-header-left">
            <div class="mf-page-icon" style="background:linear-gradient(140deg,#b45309,#f59e0b);">
                <i class="fas fa-pen"></i>
            </div>
            <div>
                <div class="mf-page-title">Edit Data <span class="mf-dim">ODP</span></div>
                <div class="mf-page-sub">{{ $odp->code }} &mdash; {{ $odp->name }}</div>
            </div>
        </div>
        <div class="mf-header-actions">
            <a href="{{ route('odps.index') }}" class="mf-btn-back">
                <i class="fas fa-arrow-left mr-1"></i> Kembali
            </a>
        </div>
    </div>

    <div class="mf-grid">

        {{-- Section: Identitas ODP --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#065f46,#10b981);">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="mf-section-title">Identitas ODP</div>
            </div>
            <div class="mf-section-body">
                <div class="mf-row mf-row-3">
                    <div class="mf-field">
                        <label class="mf-label">Owner Data <span class="mf-req">*</span></label>
                        <select name="owner_id" id="odp-owner-id" class="mf-input @error('owner_id') mf-input-error @enderror" required>
                            @foreach($owners as $owner)
                                <option value="{{ $owner->id }}" @selected(old('owner_id', $odp->owner_id) == $owner->id)>{{ $owner->name }}</option>
                            @endforeach
                        </select>
                        @error('owner_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Kode ODP <span class="mf-req">*</span></label>
                        <div style="display:flex;gap:0;">
                            <input type="text" name="code" id="odp-code" value="{{ old('code', $odp->code) }}" class="mf-input @error('code') mf-input-error @enderror" style="border-radius:9px 0 0 9px;" required>
                            <button type="button" class="btn btn-outline-secondary" id="btn-generate-odp-code" title="Generate otomatis dari titik map"
                                style="border-radius:0 9px 9px 0;border-left:none;height:38px;flex-shrink:0;">
                                <i class="fas fa-magic"></i>
                            </button>
                        </div>
                        @error('code')<div class="mf-feedback">{{ $message }}</div>@enderror
                        <span class="mf-hint" id="odp-code-result">Format otomatis: KODELOKASI-WILAYAH-001</span>
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Nama ODP <span class="mf-req">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $odp->name) }}" class="mf-input @error('name') mf-input-error @enderror" required>
                        @error('name')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mf-row mf-row-3">
                    <div class="mf-field">
                        <label class="mf-label">Area <span class="mf-opt">Opsional</span></label>
                        <input type="text" name="area" id="odp-area" value="{{ old('area', $odp->area) }}" class="mf-input @error('area') mf-input-error @enderror">
                        @error('area')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Kapasitas Port</label>
                        <input type="number" name="capacity_ports" value="{{ old('capacity_ports', $odp->capacity_ports) }}" min="0" class="mf-input @error('capacity_ports') mf-input-error @enderror">
                        @error('capacity_ports')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Status</label>
                        <select name="status" class="mf-input @error('status') mf-input-error @enderror">
                            <option value="active" @selected(old('status', $odp->status) === 'active')>Active</option>
                            <option value="maintenance" @selected(old('status', $odp->status) === 'maintenance')>Maintenance</option>
                            <option value="inactive" @selected(old('status', $odp->status) === 'inactive')>Inactive</option>
                        </select>
                        @error('status')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Section: Lokasi --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#065f46,#10b981);">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <div class="mf-section-title">Lokasi ODP</div>
            </div>
            <div class="mf-section-body">
                <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:.85rem;align-items:end;">
                    <div class="mf-field">
                        <label class="mf-label">Latitude</label>
                        <input type="text" name="latitude" id="odp-latitude" value="{{ old('latitude', $odp->latitude) }}" class="mf-input @error('latitude') mf-input-error @enderror" placeholder="-7.1234567">
                        @error('latitude')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Longitude</label>
                        <input type="text" name="longitude" id="odp-longitude" value="{{ old('longitude', $odp->longitude) }}" class="mf-input @error('longitude') mf-input-error @enderror" placeholder="109.1234567">
                        @error('longitude')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-primary" id="btn-capture-odp-gps" style="height:38px;white-space:nowrap;">
                            <i class="fas fa-location-arrow mr-1"></i>Ambil Titik
                        </button>
                    </div>
                </div>
                <span class="mf-hint" id="odp-gps-result">Klik "Ambil Titik" saat berada di lokasi ODP.</span>
                <div id="odp-location-map"></div>
                <span class="mf-hint" id="odp-map-result">Gunakan layer Earth untuk cek visual satelit. Marker bisa digeser untuk koreksi titik presisi.</span>
                <div class="mf-field">
                    <label class="mf-label">Catatan <span class="mf-opt">Opsional</span></label>
                    <textarea name="notes" class="mf-input @error('notes') mf-input-error @enderror" rows="3">{{ old('notes', $odp->notes) }}</textarea>
                    @error('notes')<div class="mf-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

    </div>

    {{-- Footer --}}
    <div class="mf-footer">
        <a href="{{ route('odps.index') }}" class="mf-btn-cancel">Batal</a>
        <button type="submit" class="mf-btn-submit">
            <i class="fas fa-save mr-1"></i> Update
        </button>
    </div>

</div>
</form>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    var map;
    var marker;
    var generatingCode = false;

    function median(values) {
        var sorted = values.slice().sort(function (a, b) { return a - b; });
        var middle = Math.floor(sorted.length / 2);
        if (sorted.length % 2 === 0) {
            return (sorted[middle - 1] + sorted[middle]) / 2;
        }
        return sorted[middle];
    }

    function parseCoordinate(value) {
        var parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : null;
    }

    function sanitizeSegment(value, fallback, maxLength) {
        var normalized = String(value || '')
            .toUpperCase()
            .replace(/[^A-Z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');

        if (!normalized) {
            normalized = fallback;
        }

        if (normalized.length > maxLength) {
            normalized = normalized.slice(0, maxLength);
        }

        return normalized;
    }

    function setCodeResult(message, className) {
        var resultElement = document.getElementById('odp-code-result');
        if (!resultElement) {
            return;
        }

        resultElement.textContent = message;
        resultElement.className = className;
    }

    function extractAreaName(address) {
        return address.suburb
            || address.village
            || address.hamlet
            || address.quarter
            || address.neighbourhood
            || address.city_district
            || address.town
            || address.city
            || address.county
            || address.state_district
            || address.state
            || '';
    }

    function extractLocationCode(address, areaName) {
        return address.city
            || address.town
            || address.county
            || address.state_district
            || address.state
            || areaName
            || 'LOC';
    }

    async function reverseGeocode(lat, lng) {
        var url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=18&addressdetails=1'
            + '&lat=' + encodeURIComponent(lat)
            + '&lon=' + encodeURIComponent(lng);
        var response = await fetch(url, {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Gagal mengambil nama wilayah dari peta.');
        }

        return response.json();
    }

    async function requestGeneratedCode(ownerId, locationCode, areaName) {
        var query = new URLSearchParams({
            owner_id: String(ownerId),
            location_code: locationCode,
            area_name: areaName,
        });
        var response = await fetch('{{ route('odps.generate-code') }}?' + query.toString(), {
            headers: {
                Accept: 'application/json',
            },
        });

        var payload = await response.json().catch(function () {
            return {};
        });

        if (!response.ok) {
            throw new Error(payload.message || 'Gagal generate kode ODP otomatis.');
        }

        return payload;
    }

    async function generateCodeFromMap() {
        if (generatingCode) {
            return;
        }

        var ownerInput = document.getElementById('odp-owner-id');
        var codeInput = document.getElementById('odp-code');
        var areaInput = document.getElementById('odp-area');
        var latInput = document.getElementById('odp-latitude');
        var lngInput = document.getElementById('odp-longitude');
        var button = document.getElementById('btn-generate-odp-code');

        if (!ownerInput || !codeInput || !latInput || !lngInput) {
            return;
        }

        var ownerId = ownerInput.value;
        var lat = parseCoordinate(latInput.value);
        var lng = parseCoordinate(lngInput.value);

        if (!ownerId) {
            setCodeResult('Owner data wajib dipilih terlebih dahulu.', 'text-danger d-block mt-1');
            return;
        }

        if (lat === null || lng === null) {
            setCodeResult('Isi titik koordinat dulu, lalu generate kode otomatis.', 'text-danger d-block mt-1');
            return;
        }

        generatingCode = true;
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }
        setCodeResult('Mengambil wilayah dari peta...', 'text-info d-block mt-1');

        try {
            var reverseData = await reverseGeocode(lat, lng);
            var address = reverseData && reverseData.address ? reverseData.address : {};
            var areaName = extractAreaName(address) || (areaInput ? areaInput.value : '') || 'Wilayah';
            var locationCodeSource = extractLocationCode(address, areaName);
            var locationCode = sanitizeSegment(locationCodeSource, 'LOC', 12);
            var areaSegmentSource = sanitizeSegment(areaName, 'WILAYAH', 40);

            if (areaInput) {
                areaInput.value = areaName;
            }

            var generated = await requestGeneratedCode(ownerId, locationCode, areaSegmentSource);
            codeInput.value = generated.code;
            setCodeResult('Kode otomatis: ' + generated.code, 'text-success d-block mt-1');
        } catch (error) {
            var message = error instanceof Error ? error.message : 'Gagal generate kode ODP otomatis.';
            setCodeResult(message, 'text-danger d-block mt-1');
        } finally {
            generatingCode = false;
            if (button) {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-magic"></i>';
            }
        }
    }

    function setCoordinates(lat, lng, shouldFocusMap) {
        var latInput = document.getElementById('odp-latitude');
        var lngInput = document.getElementById('odp-longitude');
        var mapResult = document.getElementById('odp-map-result');

        latInput.value = lat.toFixed(7);
        lngInput.value = lng.toFixed(7);

        if (marker) {
            marker.setLatLng([lat, lng]);
        }
        if (shouldFocusMap && map) {
            map.setView([lat, lng], Math.max(map.getZoom(), 17));
        }

        if (mapResult) {
            mapResult.textContent = 'Titik disetel: ' + lat.toFixed(7) + ', ' + lng.toFixed(7);
            mapResult.className = 'text-success d-block mb-3';
        }
    }

    function initLocationMap() {
        var mapContainer = document.getElementById('odp-location-map');
        if (!mapContainer || typeof L === 'undefined') {
            return;
        }

        var latInput = document.getElementById('odp-latitude');
        var lngInput = document.getElementById('odp-longitude');
        var lat = parseCoordinate(latInput.value);
        var lng = parseCoordinate(lngInput.value);
        var initialPoint = (lat !== null && lng !== null) ? [lat, lng] : [-7.36, 109.90];
        var initialZoom = (lat !== null && lng !== null) ? 16 : 12;

        map = L.map('odp-location-map').setView(initialPoint, initialZoom);
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
            Earth: earthLayer
        }).addTo(map);

        marker = L.marker(initialPoint, { draggable: true }).addTo(map);

        marker.on('dragend', function () {
            var position = marker.getLatLng();
            setCoordinates(position.lat, position.lng, false);
        });

        map.on('click', function (event) {
            setCoordinates(event.latlng.lat, event.latlng.lng, false);
        });

        latInput.addEventListener('change', function () {
            var nextLat = parseCoordinate(latInput.value);
            var nextLng = parseCoordinate(lngInput.value);

            if (nextLat === null || nextLng === null) {
                return;
            }

            setCoordinates(nextLat, nextLng, true);
        });

        lngInput.addEventListener('change', function () {
            var nextLat = parseCoordinate(latInput.value);
            var nextLng = parseCoordinate(lngInput.value);

            if (nextLat === null || nextLng === null) {
                return;
            }

            setCoordinates(nextLat, nextLng, true);
        });
    }

    function captureOdpGps() {
        var btn = document.getElementById('btn-capture-odp-gps');
        var result = document.getElementById('odp-gps-result');

        if (!navigator.geolocation) {
            result.textContent = 'Browser tidak mendukung geolocation.';
            result.className = 'text-danger d-block mb-3';
            return;
        }

        btn.disabled = true;
        result.textContent = 'Mengambil 3 sampel GPS...';
        result.className = 'text-info d-block mb-3';

        var samples = [];

        function takeSample() {
            navigator.geolocation.getCurrentPosition(function (position) {
                samples.push({
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    acc: position.coords.accuracy,
                });

                if (samples.length < 3) {
                    result.textContent = 'Sampel ' + samples.length + '/3 berhasil, melanjutkan...';
                    setTimeout(takeSample, 1200);
                    return;
                }

                var latValues = samples.map(function (s) { return s.lat; });
                var lngValues = samples.map(function (s) { return s.lng; });
                var accValues = samples.map(function (s) { return s.acc; });

                setCoordinates(median(latValues), median(lngValues), true);
                generateCodeFromMap();
                result.textContent = 'Titik diambil. Akurasi median: ' + median(accValues).toFixed(1) + ' meter.';
                result.className = 'text-success d-block mb-3';
                btn.disabled = false;
            }, function (error) {
                var message = 'Gagal mengambil GPS.';
                if (error.code === 1) message = 'Izin lokasi ditolak.';
                if (error.code === 2) message = 'Lokasi tidak tersedia.';
                if (error.code === 3) message = 'Permintaan lokasi timeout.';
                result.textContent = message;
                result.className = 'text-danger d-block mb-3';
                btn.disabled = false;
            }, {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0,
            });
        }

        takeSample();
    }

    document.addEventListener('DOMContentLoaded', function () {
        initLocationMap();
        var btn = document.getElementById('btn-capture-odp-gps');
        var generateCodeBtn = document.getElementById('btn-generate-odp-code');
        if (btn) {
            btn.addEventListener('click', captureOdpGps);
        }
        if (generateCodeBtn) {
            generateCodeBtn.addEventListener('click', function () {
                generateCodeFromMap();
            });
        }
    });
})();
</script>
@endpush
