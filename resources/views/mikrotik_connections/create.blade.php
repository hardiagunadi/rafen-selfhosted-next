@extends('layouts.admin')

@section('title', 'Tambah Router NAS')

@section('content')
<div class="mf-page">

    {{-- Page header --}}
    <div class="mf-page-header">
        <div class="mf-page-header-left">
            <div class="mf-page-icon">
                <i class="fas fa-plus"></i>
            </div>
            <div>
                <div class="mf-page-title">Tambah Router <span class="mf-dim">[NAS]</span></div>
                <div class="mf-page-sub">Konfigurasi koneksi MikroTik baru ke sistem Rafen</div>
            </div>
        </div>
        <a href="{{ route('mikrotik-connections.index') }}" class="mf-btn-back">
            <i class="fas fa-arrow-left mr-1"></i> Kembali
        </a>
    </div>

    <form action="{{ route('mikrotik-connections.store') }}" method="POST" id="mikrotik-form">
    @csrf

    {{-- Validation errors --}}
    @if ($errors->any())
    <div class="mf-alert mf-alert-danger">
        <i class="fas fa-exclamation-circle mr-2"></i>
        <div>
            <strong>Data belum valid:</strong>
            <ul class="mb-0 mt-1 pl-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    {{-- Info kredensial --}}
    <div class="mf-alert mf-alert-info">
        <i class="fas fa-info-circle mr-2" style="flex-shrink:0;margin-top:2px;"></i>
        <span>Kredensial API &amp; Secret RADIUS akan dibuat otomatis setelah router disimpan dan bisa dilihat di halaman <strong>Edit Router</strong>.</span>
    </div>
    <input type="hidden" name="username" value="{{ old('username') }}" id="auto-username">
    <input type="hidden" name="password" value="{{ old('password') }}" id="auto-password">
    <input type="hidden" name="radius_secret" value="{{ old('radius_secret') }}" id="auto-secret">

    <div class="mf-grid">

        {{-- Section: Identitas --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#0369a1,#0ea5e9);">
                    <i class="fas fa-tag"></i>
                </div>
                <div class="mf-section-title">Identitas Router</div>
            </div>
            <div class="mf-section-body">
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Nama Router <span class="mf-req">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}"
                            class="mf-input @error('name') mf-input-error @enderror"
                            placeholder="Contoh: NAS SITE-1" required>
                        @error('name')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Zona Waktu</label>
                        <select name="timezone" class="mf-input @error('timezone') mf-input-error @enderror">
                            <option value="+07:00 Asia/Jakarta" @selected(old('timezone', '+07:00 Asia/Jakarta') === '+07:00 Asia/Jakarta')>+07:00 Asia/Jakarta (WIB)</option>
                            <option value="+08:00 Asia/Makassar" @selected(old('timezone') === '+08:00 Asia/Makassar')>+08:00 Asia/Makassar (WITA)</option>
                        </select>
                        @error('timezone')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">IP / Hostname Router <span class="mf-req">*</span></label>
                        <input type="text" name="host" value="{{ old('host') }}"
                            class="mf-input @error('host') mf-input-error @enderror"
                            placeholder="192.168.1.1 atau mydomain.com" required>
                        @error('host')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Deskripsi</label>
                        <input type="text" name="notes" value="{{ old('notes') }}"
                            class="mf-input @error('notes') mf-input-error @enderror"
                            placeholder="Catatan singkat tentang router ini">
                        @error('notes')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">URL Info Isolir <span class="mf-opt">opsional</span></label>
                        <input type="text" name="isolir_url" value="{{ old('isolir_url') }}"
                            class="mf-input @error('isolir_url') mf-input-error @enderror"
                            placeholder="mydomain.com/expired.html">
                        <div class="mf-hint">Path URL tanpa http:// atau https://</div>
                        @error('isolir_url')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field mf-field-auto">
                        <label class="mf-label d-block">Status</label>
                        <label class="mf-switch">
                            <input type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', true))>
                            <span class="mf-switch-track"></span>
                            <span class="mf-switch-label">Router Aktif</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section: API Connection --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#065f46,#10b981);">
                    <i class="fas fa-plug"></i>
                </div>
                <div class="mf-section-title">Koneksi API MikroTik</div>
            </div>
            <div class="mf-section-body">
                <div class="mf-row mf-row-3">
                    <div class="mf-field">
                        <label class="mf-label">
                            Port API
                            <span class="mf-warn-chip" title="Port default mudah dipindai. Gunakan port custom untuk keamanan.">
                                <i class="fas fa-shield-alt mr-1"></i>Keamanan
                            </span>
                        </label>
                        <input type="number" name="api_port" id="api_port"
                            value="{{ old('api_port', 8728) }}"
                            class="mf-input @error('api_port') mf-input-error @enderror"
                            placeholder="8728">
                        <div class="mf-hint">Default: 8728</div>
                        @error('api_port')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field" id="ssl-port-group" style="display:none;">
                        <label class="mf-label">
                            Port API SSL
                            <span class="mf-warn-chip"><i class="fas fa-shield-alt mr-1"></i>Keamanan</span>
                        </label>
                        <input type="number" name="api_ssl_port" id="api_ssl_port"
                            value="{{ old('api_ssl_port', 8729) }}"
                            class="mf-input @error('api_ssl_port') mf-input-error @enderror"
                            placeholder="8729">
                        <div class="mf-hint">Default: 8729</div>
                        @error('api_ssl_port')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Timeout (detik)</label>
                        <input type="number" name="api_timeout"
                            value="{{ old('api_timeout', 10) }}"
                            class="mf-input @error('api_timeout') mf-input-error @enderror">
                        @error('api_timeout')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Versi RouterOS</label>
                        <select name="ros_version" class="mf-input @error('ros_version') mf-input-error @enderror" required>
                            <option value="auto" @selected(old('ros_version', 'auto') === 'auto')>Auto Detect</option>
                            <option value="7" @selected(old('ros_version') === '7')>ROS 7</option>
                            <option value="6" @selected(old('ros_version') === '6')>ROS 6</option>
                        </select>
                        @error('ros_version')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mf-row">
                    <div class="mf-field mf-field-auto">
                        <label class="mf-label d-block">SSL</label>
                        <label class="mf-switch">
                            <input type="checkbox" class="custom-control-input" name="use_ssl" value="1" id="use_ssl" @checked(old('use_ssl'))>
                            <span class="mf-switch-track"></span>
                            <span class="mf-switch-label">Gunakan SSL API</span>
                        </label>
                    </div>
                </div>

                <div class="mf-alert mf-alert-warn" id="api-port-warning" style="display:none;">
                    <i class="fas fa-exclamation-triangle mr-2" style="flex-shrink:0;margin-top:2px;"></i>
                    <span>
                        <strong>Port API masih default (8728/8729).</strong>
                        Port default rentan dipindai &amp; brute-force. Ubah di MikroTik:
                        <code>/ip service set api port=&lt;PORT_BARU&gt;</code>
                    </span>
                </div>

                {{-- Tes Koneksi --}}
                <div class="mf-test-bar">
                    <button type="button" class="mf-btn-test" id="test-connection-btn">
                        <i class="fas fa-satellite-dish mr-1"></i> Tes Koneksi
                    </button>
                    <div id="test-connection-result" class="mf-test-result d-none"></div>
                </div>
            </div>
        </div>

        {{-- Section: RADIUS --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#5b21b6,#8b5cf6);">
                    <i class="fas fa-key"></i>
                </div>
                <div class="mf-section-title">RADIUS</div>
            </div>
            <div class="mf-section-body">
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Authentication Port</label>
                        <input type="number" name="auth_port"
                            value="{{ old('auth_port', 1812) }}"
                            class="mf-input @error('auth_port') mf-input-error @enderror">
                        @error('auth_port')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Accounting Port</label>
                        <input type="number" name="acct_port"
                            value="{{ old('acct_port', 1813) }}"
                            class="mf-input @error('acct_port') mf-input-error @enderror">
                        @error('acct_port')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

    </div>{{-- /mf-grid --}}

    {{-- Footer actions --}}
    <div class="mf-footer">
        <a href="{{ route('mikrotik-connections.index') }}" class="mf-btn-cancel">Batal</a>
        <button type="submit" class="mf-btn-submit">
            <i class="fas fa-save mr-1"></i> Simpan Router
        </button>
    </div>

    </form>
</div>

<style>
/* ── Page ────────────────────────────────────────────────────────── */
.mf-page { display:flex; flex-direction:column; gap:1.1rem; }

/* ── Page header ─────────────────────────────────────────────────── */
.mf-page-header {
    display:flex; align-items:center; justify-content:space-between;
    flex-wrap:wrap; gap:.75rem;
}
.mf-page-header-left { display:flex; align-items:center; gap:.85rem; }
.mf-page-icon {
    width:46px; height:46px; border-radius:13px;
    display:flex; align-items:center; justify-content:center;
    font-size:1.15rem; color:#fff; flex-shrink:0;
    background:linear-gradient(140deg,#0369a1,#0ea5e9);
    box-shadow:0 4px 14px rgba(14,165,233,.3);
}
.mf-page-title { font-size:1.15rem; font-weight:700; color:var(--app-text,#0f172a); line-height:1.2; }
.mf-dim { color:var(--app-text-soft,#5b6b83); font-weight:500; }
.mf-page-sub { font-size:.8rem; color:var(--app-text-soft,#5b6b83); margin-top:.15rem; }
.mf-btn-back {
    display:inline-flex; align-items:center;
    padding:.4rem .95rem; border-radius:9px;
    border:1px solid var(--app-border,#d7e1ee);
    background:#fff; color:var(--app-text,#0f172a);
    font-size:.82rem; font-weight:600; text-decoration:none;
    transition:background 140ms, transform 140ms;
}
.mf-btn-back:hover { background:#f1f5ff; transform:translateY(-1px); color:var(--app-text,#0f172a); text-decoration:none; }

/* ── Alerts ──────────────────────────────────────────────────────── */
.mf-alert {
    display:flex; align-items:flex-start; gap:.5rem;
    padding:.85rem 1rem; border-radius:12px; font-size:.84rem;
}
.mf-alert-danger { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
.mf-alert-info   { background:#eff6ff; border:1px solid #bfdbfe; color:#1e4d78; }
.mf-alert-warn   { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }

/* ── Grid of sections ────────────────────────────────────────────── */
.mf-grid { display:flex; flex-direction:column; gap:1rem; }

/* ── Section card ────────────────────────────────────────────────── */
.mf-section {
    background:var(--app-surface,#fff);
    border:1px solid var(--app-border,#d7e1ee);
    border-radius:16px;
    box-shadow:0 4px 16px rgba(15,23,42,.05);
    overflow:hidden;
}
.mf-section-header {
    display:flex; align-items:center; gap:.75rem;
    padding:.8rem 1.25rem;
    background:#f8fbff;
    border-bottom:1px solid var(--app-border,#d7e1ee);
}
.mf-section-icon {
    width:34px; height:34px; border-radius:9px;
    display:flex; align-items:center; justify-content:center;
    font-size:.9rem; color:#fff; flex-shrink:0;
}
.mf-section-title { font-size:.9rem; font-weight:700; color:var(--app-text,#0f172a); }
.mf-section-body { padding:1.1rem 1.25rem; display:flex; flex-direction:column; gap:.85rem; }

/* ── Row & field ─────────────────────────────────────────────────── */
.mf-row {
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:.85rem;
}
.mf-row-3 { grid-template-columns:repeat(2,1fr); }
@media (min-width:992px) { .mf-row-3 { grid-template-columns:repeat(4,1fr); } }
@media (max-width:767px) { .mf-row, .mf-row-3 { grid-template-columns:1fr; } }
.mf-field { display:flex; flex-direction:column; gap:.3rem; }
.mf-field-auto { justify-content:flex-end; }

/* ── Label ───────────────────────────────────────────────────────── */
.mf-label {
    font-size:.77rem; font-weight:700;
    text-transform:uppercase; letter-spacing:.04em;
    color:var(--app-text-soft,#5b6b83);
    display:flex; align-items:center; gap:.4rem;
}
.mf-req { color:#ef4444; font-size:.85em; }
.mf-opt {
    font-size:.7rem; font-weight:600; border-radius:20px;
    padding:.1rem .45rem; background:rgba(100,116,139,.1);
    color:#64748b; text-transform:none; letter-spacing:0;
}
.mf-warn-chip {
    font-size:.68rem; font-weight:600; border-radius:20px;
    padding:.15rem .5rem;
    background:rgba(245,158,11,.15); border:1px solid rgba(245,158,11,.3);
    color:#92400e; text-transform:none; letter-spacing:0;
    cursor:default;
}
.mf-hint { font-size:.73rem; color:var(--app-text-soft,#5b6b83); }

/* ── Input ───────────────────────────────────────────────────────── */
.mf-input {
    height:38px; border-radius:9px;
    border:1px solid var(--app-border,#d7e1ee);
    padding:0 .75rem; font-size:.85rem;
    color:var(--app-text,#0f172a); background:#fff;
    outline:none; width:100%;
    transition:border-color 150ms, box-shadow 150ms;
}
select.mf-input { appearance:auto; }
textarea.mf-input { height:auto; padding:.5rem .75rem; resize:vertical; }
.mf-input:focus {
    border-color:#8fb5df;
    box-shadow:0 0 0 3px rgba(19,103,164,.12);
}
.mf-input-error { border-color:#f43f5e !important; }
.mf-feedback { font-size:.76rem; color:#dc2626; margin-top:.1rem; }

/* ── Switch ──────────────────────────────────────────────────────── */
.mf-switch {
    display:inline-flex; align-items:center; gap:.6rem;
    cursor:pointer; user-select:none; height:38px;
}
.mf-switch input { display:none; }
.mf-switch-track {
    width:42px; height:24px; border-radius:99px; flex-shrink:0;
    background:#d1d5db; transition:background 200ms;
    position:relative;
}
.mf-switch-track::after {
    content:''; position:absolute;
    top:3px; left:3px; width:18px; height:18px;
    border-radius:50%; background:#fff;
    box-shadow:0 1px 3px rgba(0,0,0,.2);
    transition:transform 200ms;
}
.mf-switch input:checked ~ .mf-switch-track { background:linear-gradient(140deg,#0369a1,#0ea5e9); }
.mf-switch input:checked ~ .mf-switch-track::after { transform:translateX(18px); }
.mf-switch-label { font-size:.84rem; font-weight:600; color:var(--app-text,#0f172a); }

/* ── Test bar ────────────────────────────────────────────────────── */
.mf-test-bar { display:flex; align-items:center; flex-wrap:wrap; gap:.75rem; margin-top:.25rem; }
.mf-btn-test {
    display:inline-flex; align-items:center;
    height:36px; padding:0 1rem; border-radius:9px; border:none;
    background:linear-gradient(140deg,#334155,#64748b);
    color:#fff; font-size:.82rem; font-weight:600;
    cursor:pointer; transition:opacity 140ms, transform 140ms;
}
.mf-btn-test:hover { opacity:.88; transform:translateY(-1px); }
.mf-test-result {
    padding:.4rem .85rem; border-radius:9px; font-size:.81rem; font-weight:500;
}
.mf-test-result.ok   { background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.3); color:#065f46; }
.mf-test-result.fail { background:rgba(239,68,68,.1);   border:1px solid rgba(239,68,68,.25); color:#991b1b; }
.mf-test-result.info { background:#eff6ff; border:1px solid #bfdbfe; color:#1e4d78; }

/* ── Footer actions ──────────────────────────────────────────────── */
.mf-footer {
    display:flex; justify-content:space-between; align-items:center;
    flex-wrap:wrap; gap:.75rem;
    padding:1rem 1.25rem;
    background:var(--app-surface,#fff);
    border:1px solid var(--app-border,#d7e1ee);
    border-radius:14px;
}
.mf-btn-cancel {
    font-size:.84rem; font-weight:600; color:var(--app-text-soft,#5b6b83);
    text-decoration:none; padding:.4rem .75rem;
    transition:color 140ms;
}
.mf-btn-cancel:hover { color:var(--app-text,#0f172a); text-decoration:none; }
.mf-btn-submit {
    display:inline-flex; align-items:center;
    height:38px; padding:0 1.4rem; border-radius:10px; border:none;
    background:linear-gradient(140deg,#0369a1,#0ea5e9);
    color:#fff; font-size:.88rem; font-weight:700;
    cursor:pointer; box-shadow:0 3px 12px rgba(14,165,233,.25);
    transition:opacity 140ms, transform 140ms;
}
.mf-btn-submit:hover { opacity:.9; transform:translateY(-1px); }
</style>

<script>
(function () {
    // ── Generate credentials ─────────────────────────────────────
    function randomString(length, charset) {
        let result = '';
        const chars = charset.split('');
        for (let i = 0; i < length; i++) {
            result += chars[Math.floor(Math.random() * chars.length)];
        }
        return result;
    }
    function generateCredentials() {
        const u = document.getElementById('auto-username');
        const p = document.getElementById('auto-password');
        const s = document.getElementById('auto-secret');
        if (!u.value) u.value = 'TMDRadius' + randomString(6, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
        if (!p.value) p.value = randomString(10, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*');
        if (!s.value) s.value = p.value;
    }

    // ── SSL toggle ───────────────────────────────────────────────
    const sslToggle  = document.getElementById('use_ssl');
    const sslGroup   = document.getElementById('ssl-port-group');
    const apiPortEl  = document.querySelector('input[name="api_port"]');
    const apiSslEl   = document.querySelector('input[name="api_ssl_port"]');
    const portWarn   = document.getElementById('api-port-warning');

    function toggleSsl() {
        sslGroup.style.display = sslToggle.checked ? '' : 'none';
        checkPortWarning();
    }
    function checkPortWarning() {
        const port    = parseInt(apiPortEl.value, 10);
        const sslPort = parseInt(apiSslEl ? apiSslEl.value : 0, 10);
        const show    = port === 8728 || (sslToggle.checked && sslPort === 8729);
        portWarn.style.display = show ? '' : 'none';
    }
    sslToggle.addEventListener('change', toggleSsl);
    apiPortEl.addEventListener('input', checkPortWarning);
    if (apiSslEl) apiSslEl.addEventListener('input', checkPortWarning);

    // ── Test connection ──────────────────────────────────────────
    const testBtn    = document.getElementById('test-connection-btn');
    const testResult = document.getElementById('test-connection-result');

    testBtn.addEventListener('click', function () {
        const host      = document.querySelector('input[name="host"]').value;
        const apiPort   = apiPortEl.value || 8728;
        const apiSslPort= apiSslEl ? apiSslEl.value : 8729;
        const useSsl    = sslToggle.checked;
        const timeout   = document.querySelector('input[name="api_timeout"]').value || 10;

        testResult.className = 'mf-test-result info';
        testResult.classList.remove('d-none');
        testResult.textContent = 'Menguji koneksi...';

        fetch('{{ route("mikrotik-connections.test") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ host, api_timeout: timeout, api_port: apiPort, api_ssl_port: apiSslPort, use_ssl: useSsl }),
        }).then(async function (response) {
            const data = await response.json();
            if (response.ok && data.success) {
                testResult.className = 'mf-test-result ok';
                testResult.innerHTML = '<i class="fas fa-check-circle mr-1"></i>' + data.message;
            } else {
                testResult.className = 'mf-test-result fail';
                const extra = data.port_open === false ? ' Port API tertutup atau salah.' : '';
                testResult.innerHTML = '<i class="fas fa-times-circle mr-1"></i>' + (data.message || 'Koneksi gagal.') + extra;
            }
        }).catch(function () {
            testResult.className = 'mf-test-result fail';
            testResult.innerHTML = '<i class="fas fa-times-circle mr-1"></i>Gagal menguji koneksi.';
        });
    });

    // ── Init ─────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        generateCredentials();
        checkPortWarning();
    });
    if (document.readyState !== 'loading') {
        generateCredentials();
        checkPortWarning();
    }
})();
</script>
@endsection
