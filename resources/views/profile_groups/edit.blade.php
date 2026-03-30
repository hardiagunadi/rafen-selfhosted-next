@extends('layouts.admin')

@section('title', 'Edit Profil Group')

@section('content')
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
.mf-switch { display:inline-flex;align-items:center;gap:.6rem;cursor:pointer;user-select:none; }
.mf-switch input { display:none; }
.mf-switch-track { width:42px;height:24px;border-radius:99px;flex-shrink:0;background:#d1d5db;transition:background 200ms;position:relative; }
.mf-switch-track::after { content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:transform 200ms; }
.mf-switch input:checked ~ .mf-switch-track { background:linear-gradient(140deg,#0369a1,#0ea5e9); }
.mf-switch input:checked ~ .mf-switch-track::after { transform:translateX(18px); }
.mf-switch-label { font-size:.84rem;font-weight:600;color:var(--app-text,#0f172a); }
.mf-footer { display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;padding:1rem 1.25rem;background:var(--app-surface,#fff);border:1px solid var(--app-border,#d7e1ee);border-radius:14px; }
.mf-btn-cancel { font-size:.84rem;font-weight:600;color:var(--app-text-soft,#5b6b83);text-decoration:none;padding:.4rem .75rem;transition:color 140ms; }
.mf-btn-cancel:hover { color:var(--app-text,#0f172a);text-decoration:none; }
.mf-btn-submit { display:inline-flex;align-items:center;height:38px;padding:0 1.4rem;border-radius:10px;border:none;background:linear-gradient(140deg,#0369a1,#0ea5e9);color:#fff;font-size:.88rem;font-weight:700;cursor:pointer;box-shadow:0 3px 12px rgba(14,165,233,.25);transition:opacity 140ms,transform 140ms; }
.mf-btn-submit:hover { opacity:.9;transform:translateY(-1px); }
</style>

<form action="{{ route('profile-groups.update', $profileGroup) }}" method="POST">
@csrf
@method('PUT')
<div class="mf-page">

    {{-- Page header --}}
    <div class="mf-page-header">
        <div class="mf-page-header-left">
            <div class="mf-page-icon" style="background:linear-gradient(140deg,#065f46,#10b981);">
                <i class="fas fa-layer-group"></i>
            </div>
            <div>
                <div class="mf-page-title">Edit Profil Group</div>
                <div class="mf-page-sub">{{ $profileGroup->name }}</div>
            </div>
        </div>
        <div class="mf-header-actions">
            <a href="{{ route('profile-groups.index') }}" class="mf-btn-back">
                <i class="fas fa-arrow-left mr-1"></i> Kembali
            </a>
        </div>
    </div>

    {{-- Validation errors --}}
    @if ($errors->any())
    <div class="mf-alert mf-alert-danger">
        <i class="fas fa-exclamation-circle mt-1"></i>
        <div>
            <strong>Terdapat kesalahan:</strong>
            <ul class="mb-0 pl-3 mt-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    <div class="mf-grid">
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#065f46,#10b981);"><i class="fas fa-layer-group"></i></div>
                <div class="mf-section-title">Identitas Group</div>
            </div>
            <div class="mf-section-body">
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Nama Group <span class="mf-req">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $profileGroup->name) }}" class="mf-input @error('name') mf-input-error @enderror" required>
                        @error('name')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Owner Data</label>
                        <select name="owner" class="mf-input @error('owner') mf-input-error @enderror">
                            <option value="">- pilih -</option>
                            @foreach($users as $user)
                                <option value="{{ $user->name }}" @selected(old('owner', $profileGroup->owner) === $user->name)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                        @error('owner')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Router (NAS)</label>
                        <select name="mikrotik_connection_id" class="mf-input @error('mikrotik_connection_id') mf-input-error @enderror">
                            <option value="">Semua Router (NAS)</option>
                            @foreach($mikrotikConnections as $conn)
                                <option value="{{ $conn->id }}" @selected(old('mikrotik_connection_id', $profileGroup->mikrotik_connection_id) == $conn->id)>{{ $conn->name }}</option>
                            @endforeach
                        </select>
                        @error('mikrotik_connection_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Tipe <span class="mf-req">*</span></label>
                        <select name="type" class="mf-input @error('type') mf-input-error @enderror">
                            <option value="hotspot" @selected(old('type', $profileGroup->type) === 'hotspot')>Hotspot</option>
                            <option value="pppoe" @selected(old('type', $profileGroup->type) === 'pppoe')>PPPoE</option>
                        </select>
                        @error('type')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#065f46,#10b981);"><i class="fas fa-network-wired"></i></div>
                <div class="mf-section-title">Konfigurasi IP Pool</div>
            </div>
            <div class="mf-section-body">
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Modul IP Pool</label>
                        <select name="ip_pool_mode" id="ip_pool_mode" class="mf-input @error('ip_pool_mode') mf-input-error @enderror">
                            <option value="group_only" @selected(old('ip_pool_mode', $profileGroup->ip_pool_mode) === 'group_only')>Group Only (Mikrotik)</option>
                            <option value="sql" @selected(old('ip_pool_mode', $profileGroup->ip_pool_mode) === 'sql')>SQL IP Pool</option>
                        </select>
                        @error('ip_pool_mode')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">IP Pool Mikrotik (nama)</label>
                        <input type="text" name="ip_pool_name" value="{{ old('ip_pool_name', $profileGroup->ip_pool_name) }}" class="mf-input @error('ip_pool_name') mf-input-error @enderror" placeholder="Pool Mikrotik">
                        @error('ip_pool_name')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div id="sql-pool-fields">
                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Range IP Awal</label>
                            <input type="text" name="range_start" value="{{ old('range_start', $profileGroup->range_start) }}" class="mf-input @error('range_start') mf-input-error @enderror" placeholder="10.0.0.2">
                            @error('range_start')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Range IP Akhir</label>
                            <input type="text" name="range_end" value="{{ old('range_end', $profileGroup->range_end) }}" class="mf-input @error('range_end') mf-input-error @enderror" placeholder="10.0.0.254">
                            @error('range_end')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="mf-row" style="margin-top:.85rem;">
                        <div class="mf-field">
                            <label class="mf-label">IP Address (SQL IP Pool)</label>
                            <input type="text" name="ip_address" value="{{ old('ip_address', $profileGroup->ip_address) }}" class="mf-input @error('ip_address') mf-input-error @enderror" placeholder="10.0.1.0">
                            @error('ip_address')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Netmask (SQL IP Pool)</label>
                            <input type="text" name="netmask" value="{{ old('netmask', $profileGroup->netmask) }}" class="mf-input @error('netmask') mf-input-error @enderror" placeholder="255.255.255.0 atau 24">
                            @error('netmask')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="mf-row">
                    <div class="mf-field" id="dns-server-group">
                        <label class="mf-label">DNS Server</label>
                        <input type="text" id="dns_servers" name="dns_servers" value="{{ old('dns_servers', $profileGroup->dns_servers ?? '8.8.8.8,8.8.4.4') }}" class="mf-input @error('dns_servers') mf-input-error @enderror" placeholder="8.8.8.8,8.8.4.4">
                        @error('dns_servers')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Parent Queue <span class="mf-opt">opsional</span></label>
                        <select name="parent_queue" id="parent_queue_select" class="mf-input @error('parent_queue') mf-input-error @enderror">
                            <option value="">Memuat dari Mikrotik...</option>
                        </select>
                        @error('parent_queue')<div class="mf-feedback">{{ $message }}</div>@enderror
                        <div class="mf-hint" id="queue-fetch-status">Kosongkan untuk menggunakan parent queue dari Profil PPP.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mf-footer">
        <a href="{{ route('profile-groups.index') }}" class="mf-btn-cancel">Batal</a>
        <button type="submit" class="mf-btn-submit"><i class="fas fa-save mr-1"></i> Update</button>
    </div>

</div>
</form>

<script>
    function syncPoolMode() {
        var type = document.querySelector('select[name="type"]').value;
        var poolModeSelect = document.getElementById('ip_pool_mode');
        var sqlFields = document.getElementById('sql-pool-fields');

        if (type === 'hotspot') {
            poolModeSelect.value = 'group_only';
            poolModeSelect.disabled = true;
            sqlFields.style.display = 'none';
        } else {
            poolModeSelect.disabled = false;
            sqlFields.style.display = poolModeSelect.value === 'sql' ? '' : 'none';
        }
    }

    function syncSqlFields() {
        var type = document.querySelector('select[name="type"]').value;
        if (type === 'hotspot') return;
        var poolMode = document.getElementById('ip_pool_mode').value;
        document.getElementById('sql-pool-fields').style.display = poolMode === 'sql' ? '' : 'none';
    }

    function syncDnsField() {
        var type = document.querySelector('select[name="type"]').value;
        var dnsGroup = document.getElementById('dns-server-group');
        var dnsInput = document.getElementById('dns_servers');
        var isHotspot = type === 'hotspot';

        if (!dnsGroup || !dnsInput) {
            return;
        }

        dnsGroup.style.display = isHotspot ? 'none' : '';
        dnsInput.disabled = isHotspot;
    }

    document.querySelector('select[name="type"]').addEventListener('change', syncPoolMode);
    document.querySelector('select[name="type"]').addEventListener('change', syncDnsField);
    document.getElementById('ip_pool_mode').addEventListener('change', syncSqlFields);

    // Init on load
    (function () {
        var type = document.querySelector('select[name="type"]').value;
        var poolMode = document.getElementById('ip_pool_mode').value;
        var poolModeSelect = document.getElementById('ip_pool_mode');
        var sqlFields = document.getElementById('sql-pool-fields');
        if (type === 'hotspot') {
            poolModeSelect.value = 'group_only';
            poolModeSelect.disabled = true;
            sqlFields.style.display = 'none';
        } else {
            sqlFields.style.display = poolMode === 'sql' ? '' : 'none';
        }

        syncDnsField();
    })();

    // --- Parent Queue: fetch otomatis dari semua Mikrotik ---
    (function fetchParentQueues() {
        var sel = document.getElementById('parent_queue_select');
        var status = document.getElementById('queue-fetch-status');
        var current = '{{ old('parent_queue', $profileGroup->parent_queue) }}';
        fetch('{{ route("profile-groups.mikrotik-queues") }}', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.error) { status.textContent = data.error; return; }
            sel.innerHTML = '<option value="">- tidak ada / dari Profil PPP -</option>';
            (data.queues || []).forEach(function (q) {
                var opt = document.createElement('option');
                opt.value = q; opt.textContent = q;
                if (q === current) opt.selected = true;
                sel.appendChild(opt);
            });
            status.textContent = 'Kosongkan untuk menggunakan parent queue dari Profil PPP.';
        })
        .catch(function () { status.textContent = 'Gagal mengambil queue dari Mikrotik.'; });
    })();
</script>
@endsection
