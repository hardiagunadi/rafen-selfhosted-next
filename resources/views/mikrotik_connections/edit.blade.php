@extends('layouts.admin')

@section('title', 'Edit Router NAS')

@section('content')
<div class="mf-page">

    {{-- Page header --}}
    <div class="mf-page-header">
        <div class="mf-page-header-left">
            <div class="mf-page-icon" style="background:linear-gradient(140deg,#b45309,#f59e0b);">
                <i class="fas fa-pen"></i>
            </div>
            <div>
                <div class="mf-page-title">Edit Router <span class="mf-dim">[NAS]</span></div>
                <div class="mf-page-sub">{{ $mikrotikConnection->name }} &mdash; {{ $mikrotikConnection->host }}</div>
            </div>
        </div>
        <div class="mf-header-actions">
            <button type="button" class="mf-btn-script" id="script-generator-btn" data-toggle="modal" data-target="#scriptGeneratorModal">
                <i class="fas fa-code mr-1"></i> Script Generator
            </button>
            <a href="{{ route('mikrotik-connections.index') }}" class="mf-btn-back">
                <i class="fas fa-arrow-left mr-1"></i> Kembali
            </a>
        </div>
    </div>

    {{-- Validation errors --}}
    @if ($errors->any())
    <div class="mf-alert mf-alert-danger">
        <i class="fas fa-exclamation-circle mr-2" style="flex-shrink:0;margin-top:2px;"></i>
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

    {{-- RADIUS mismatch warning --}}
    @if($radiusSecretMismatch ?? false)
    <div class="mf-alert mf-alert-warn" id="radius-mismatch-alert">
        <i class="fas fa-exclamation-triangle mr-2" style="flex-shrink:0;margin-top:2px;"></i>
        <div style="flex:1;">
            <strong>Secret RADIUS tidak sinkron!</strong>
            Secret di <code>clients.conf</code> belum cocok dengan DB — MikroTik tidak bisa autentikasi ke RADIUS.
        </div>
        <button type="button" class="mf-btn-sync" id="sync-radius-clients-btn">
            <i class="fas fa-sync mr-1"></i> Sync RADIUS
        </button>
    </div>
    @endif

    {{-- Credentials info --}}
    <div class="mf-cred-bar">
        <div class="mf-cred-item">
            <span class="mf-cred-label">Username API</span>
            <code class="mf-cred-value" id="generated-username">{{ $mikrotikConnection->username }}</code>
        </div>
        <div class="mf-cred-sep"></div>
        <div class="mf-cred-item">
            <span class="mf-cred-label">Password API &amp; Secret RADIUS</span>
            <code class="mf-cred-value" id="generated-secret">{{ $mikrotikConnection->password }}</code>
        </div>
    </div>

    <form action="{{ route('mikrotik-connections.update', $mikrotikConnection) }}" method="POST" id="mikrotik-form">
    @csrf @method('PUT')

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
                        <input type="text" name="name" value="{{ old('name', $mikrotikConnection->name) }}"
                            class="mf-input @error('name') mf-input-error @enderror" required>
                        @error('name')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Zona Waktu</label>
                        <select name="timezone" class="mf-input @error('timezone') mf-input-error @enderror">
                            <option value="+07:00 Asia/Jakarta" @selected(old('timezone', $mikrotikConnection->timezone) === '+07:00 Asia/Jakarta')>+07:00 Asia/Jakarta (WIB)</option>
                            <option value="+08:00 Asia/Makassar" @selected(old('timezone', $mikrotikConnection->timezone) === '+08:00 Asia/Makassar')>+08:00 Asia/Makassar (WITA)</option>
                        </select>
                        @error('timezone')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">IP / Hostname Router <span class="mf-req">*</span></label>
                        <input type="text" name="host" value="{{ old('host', $mikrotikConnection->host) }}"
                            class="mf-input @error('host') mf-input-error @enderror" required>
                        @error('host')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Deskripsi</label>
                        <input type="text" name="notes" value="{{ old('notes', $mikrotikConnection->notes) }}"
                            class="mf-input @error('notes') mf-input-error @enderror"
                            placeholder="Catatan singkat tentang router ini">
                        @error('notes')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mf-row">
                    <div class="mf-field mf-field-auto">
                        <label class="mf-label d-block">Status</label>
                        <label class="mf-switch">
                            <input type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $mikrotikConnection->is_active))>
                            <span class="mf-switch-track"></span>
                            <span class="mf-switch-label">Router Aktif</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section: Kredensial API --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#334155,#64748b);">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="mf-section-title">Kredensial API</div>
            </div>
            <div class="mf-section-body">
                <div class="mf-row mf-row-3">
                    <div class="mf-field">
                        <label class="mf-label">Username API <span class="mf-req">*</span></label>
                        <input type="text" name="username" value="{{ old('username', $mikrotikConnection->username) }}"
                            class="mf-input @error('username') mf-input-error @enderror" required>
                        @error('username')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Password API <span class="mf-req">*</span></label>
                        <input type="text" name="password" value="{{ old('password', $mikrotikConnection->password) }}"
                            class="mf-input @error('password') mf-input-error @enderror" required>
                        @error('password')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Secret RADIUS</label>
                        <input type="text" name="radius_secret" value="{{ old('radius_secret', $mikrotikConnection->radius_secret) }}"
                            class="mf-input @error('radius_secret') mf-input-error @enderror"
                            placeholder="Kosongkan jika tidak ingin mengubah">
                        @error('radius_secret')<div class="mf-feedback">{{ $message }}</div>@enderror
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
                <div class="mf-row mf-row-4">
                    <div class="mf-field">
                        <label class="mf-label">
                            Port API
                            <span class="mf-warn-chip"><i class="fas fa-shield-alt mr-1"></i>Keamanan</span>
                        </label>
                        <input type="number" name="api_port" id="api_port"
                            value="{{ old('api_port', $mikrotikConnection->api_port) }}"
                            class="mf-input @error('api_port') mf-input-error @enderror">
                        <div class="mf-hint">Default: 8728</div>
                        @error('api_port')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field" id="ssl-port-group" style="{{ old('use_ssl', $mikrotikConnection->use_ssl) ? '' : 'display:none' }}">
                        <label class="mf-label">
                            Port API SSL
                            <span class="mf-warn-chip"><i class="fas fa-shield-alt mr-1"></i>Keamanan</span>
                        </label>
                        <input type="number" name="api_ssl_port" id="api_ssl_port"
                            value="{{ old('api_ssl_port', $mikrotikConnection->api_ssl_port) }}"
                            class="mf-input @error('api_ssl_port') mf-input-error @enderror">
                        <div class="mf-hint">Default: 8729</div>
                        @error('api_ssl_port')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Timeout (detik)</label>
                        <input type="number" name="api_timeout"
                            value="{{ old('api_timeout', $mikrotikConnection->api_timeout) }}"
                            class="mf-input @error('api_timeout') mf-input-error @enderror">
                        @error('api_timeout')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Versi RouterOS</label>
                        <select name="ros_version" class="mf-input @error('ros_version') mf-input-error @enderror" required>
                            <option value="auto" @selected(old('ros_version', $mikrotikConnection->ros_version) === 'auto')>Auto Detect</option>
                            <option value="7" @selected(old('ros_version', $mikrotikConnection->ros_version) === '7')>ROS 7</option>
                            <option value="6" @selected(old('ros_version', $mikrotikConnection->ros_version) === '6')>ROS 6</option>
                        </select>
                        @error('ros_version')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mf-row">
                    <div class="mf-field mf-field-auto">
                        <label class="mf-label d-block">SSL</label>
                        <label class="mf-switch">
                            <input type="checkbox" name="use_ssl" value="1" id="use_ssl" @checked(old('use_ssl', $mikrotikConnection->use_ssl))>
                            <span class="mf-switch-track"></span>
                            <span class="mf-switch-label">Gunakan SSL API</span>
                        </label>
                    </div>
                </div>
                <div class="mf-alert mf-alert-warn" id="api-port-warning" style="display:none;">
                    <i class="fas fa-exclamation-triangle mr-2" style="flex-shrink:0;margin-top:2px;"></i>
                    <span><strong>Port API masih default (8728/8729).</strong> Gunakan <strong>Script Generator</strong> untuk mendapatkan perintah ganti port sekaligus konfigurasi RADIUS.</span>
                </div>
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
                            value="{{ old('auth_port', $mikrotikConnection->auth_port ?? 1812) }}"
                            class="mf-input @error('auth_port') mf-input-error @enderror">
                        @error('auth_port')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Accounting Port</label>
                        <input type="number" name="acct_port"
                            value="{{ old('acct_port', $mikrotikConnection->acct_port ?? 1813) }}"
                            class="mf-input @error('acct_port') mf-input-error @enderror">
                        @error('acct_port')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Section: Isolir --}}
        <div class="mf-section mf-section-isolir">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#be123c,#f43f5e);">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="mf-section-title">Pengaturan Isolir</div>
                <div class="ml-auto">
                    @if($mikrotikConnection->isolir_setup_done)
                        <span class="mf-status-chip mf-status-done">
                            <i class="fas fa-check mr-1"></i>Setup selesai {{ $mikrotikConnection->isolir_setup_at?->format('d/m/Y H:i') }}
                        </span>
                    @elseif($mikrotikConnection->isolir_pool_name)
                        <span class="mf-status-chip mf-status-ready">
                            <i class="fas fa-hourglass-half mr-1"></i>Siap &mdash; akan diterapkan saat isolir pertama
                        </span>
                    @else
                        <span class="mf-status-chip mf-status-pending">
                            <i class="fas fa-cog mr-1"></i>Belum dikonfigurasi
                        </span>
                    @endif
                </div>
            </div>
            <div class="mf-section-body">
                <p class="mf-section-desc">Isi konfigurasi di bawah, kemudian simpan. Setup akan diterapkan ke router <strong>otomatis</strong> saat user pertama diisolir.</p>
                <div class="mf-field" style="max-width:520px;">
                    <label class="mf-label">URL Halaman Isolir <span class="mf-opt">opsional</span></label>
                    <input type="text" name="isolir_url" value="{{ old('isolir_url', $mikrotikConnection->isolir_url) }}"
                        class="mf-input @error('isolir_url') mf-input-error @enderror"
                        placeholder="Kosongkan = halaman bawaan Rafen ({{ parse_url(config('app.url'), PHP_URL_HOST) }})">
                    <div class="mf-hint">Isi jika ingin redirect ke halaman lain (format: <code>host</code> atau <code>host:port</code>)</div>
                    @error('isolir_url')<div class="mf-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mf-row mf-row-3">
                    <div class="mf-field">
                        <label class="mf-label">Nama IP Pool</label>
                        <input type="text" name="isolir_pool_name"
                            value="{{ old('isolir_pool_name', $mikrotikConnection->isolir_pool_name ?: 'pool-isolir') }}"
                            class="mf-input" placeholder="pool-isolir">
                        <div class="mf-hint">Nama pool di MikroTik</div>
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Range IP Pool</label>
                        <input type="text" name="isolir_pool_range"
                            value="{{ old('isolir_pool_range', $mikrotikConnection->isolir_pool_range ?: '10.99.0.2-10.99.0.254') }}"
                            class="mf-input" placeholder="10.99.0.2-10.99.0.254">
                        <div class="mf-hint">Range IP untuk user isolir</div>
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Gateway Pool</label>
                        <input type="text" name="isolir_gateway"
                            value="{{ old('isolir_gateway', $mikrotikConnection->isolir_gateway ?: '10.99.0.1') }}"
                            class="mf-input" placeholder="10.99.0.1">
                        <div class="mf-hint">Local address PPP profile</div>
                    </div>
                </div>
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Nama PPP Profile Isolir</label>
                        <input type="text" name="isolir_profile_name"
                            value="{{ old('isolir_profile_name', $mikrotikConnection->isolir_profile_name ?: 'isolir-pppoe') }}"
                            class="mf-input" placeholder="isolir-pppoe">
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Batas Bandwidth</label>
                        <input type="text" name="isolir_rate_limit"
                            value="{{ old('isolir_rate_limit', $mikrotikConnection->isolir_rate_limit ?: '128k/128k') }}"
                            class="mf-input" placeholder="128k/128k">
                        <div class="mf-hint">Upload/Download</div>
                    </div>
                </div>
                @if($mikrotikConnection->isolir_setup_done)
                <div class="mf-alert mf-alert-warn" style="align-items:center;">
                    <i class="fas fa-info-circle mr-2" style="flex-shrink:0;"></i>
                    <span style="flex:1;">Jika mengubah konfigurasi, setup akan dijalankan ulang saat user berikutnya diisolir.</span>
                    <button type="button" class="mf-btn-reset-isolir"
                        onclick="if(confirm('Reset status setup isolir? Router akan di-setup ulang saat user berikutnya diisolir.')){document.getElementById('isolir-reset-form').submit();}">
                        <i class="fas fa-redo mr-1"></i> Reset Setup
                    </button>
                </div>
                @endif
            </div>
        </div>

    </div>{{-- /mf-grid --}}

    {{-- Footer actions --}}
    <div class="mf-footer">
        <a href="{{ route('mikrotik-connections.index') }}" class="mf-btn-cancel">Batal</a>
        <button type="submit" class="mf-btn-submit">
            <i class="fas fa-save mr-1"></i> Update Router
        </button>
    </div>

    </form>

    @if($mikrotikConnection->isolir_setup_done)
        <form action="{{ route('mikrotik-connections.isolir-reset', $mikrotikConnection) }}" method="POST" id="isolir-reset-form" class="d-none">
            @csrf
        </form>
    @endif

    {{-- WireGuard Section --}}
    @php
        $hasWgPeer    = (bool) $mikrotikConnection->wgPeer;
        $connIsOnline = $mikrotikConnection->is_online ?? null;
        $connUnstable = (bool) ($mikrotikConnection->ping_unstable ?? false);
    @endphp

    @if(! $hasWgPeer)
    <div class="mf-alert mf-alert-info" style="margin-top:.25rem;">
        <i class="fas fa-shield-alt mr-2" style="flex-shrink:0;margin-top:2px;"></i>
        <span style="flex:1;">Router ini menggunakan <strong>IP publik</strong> langsung. Opsional: gunakan <strong>WireGuard Tunnel</strong> agar RADIUS terhubung via jaringan privat.</span>
        <a href="{{ route('settings.wg') }}?router_id={{ $mikrotikConnection->id }}&router_name={{ urlencode($mikrotikConnection->name) }}"
           class="mf-btn-wg-create">
            <i class="fas fa-plus mr-1"></i> Buat Tunnel
        </a>
    </div>
    @endif

    @if($hasWgPeer)
    @php $wgPeer = $mikrotikConnection->wgPeer; @endphp
    <div class="mf-section" style="margin-top:.25rem;">
        <div class="mf-section-header">
            <div class="mf-section-icon" style="background:linear-gradient(140deg,#0e7490,#06b6d4);">
                <i class="fas fa-network-wired"></i>
            </div>
            <div class="mf-section-title">WireGuard Tunnel</div>
            <div class="ml-auto">
                @if($connIsOnline === null)
                    <span class="mf-status-chip mf-status-pending">Belum Dicek</span>
                @elseif($connUnstable)
                    <span class="mf-status-chip mf-status-warn">Tidak Stabil</span>
                @elseif($connIsOnline)
                    <span class="mf-status-chip mf-status-done">Terhubung</span>
                @else
                    <span class="mf-status-chip mf-status-fail">Tidak Terhubung</span>
                @endif
            </div>
        </div>
        <div class="mf-section-body">
            @if($connIsOnline === null)
                <div class="mf-alert mf-alert-info" style="align-items:center;">
                    <i class="fas fa-clock mr-2" style="flex-shrink:0;"></i>
                    <span style="flex:1;">Tunnel terdaftar. Status akan dicek otomatis setiap <strong>5 menit</strong>.</span>
                    <button type="button" class="mf-btn-test" id="wg-ping-now-btn">
                        <i class="fas fa-satellite-dish mr-1"></i> Cek Sekarang
                    </button>
                </div>
            @elseif($connIsOnline && ! $connUnstable)
                <div class="mf-alert mf-alert-ok">
                    <i class="fas fa-check-circle mr-2"></i>
                    Tunnel <strong>terhubung</strong>. Ping konsisten. RADIUS NAS menggunakan IP <strong>{{ $wgPeer->vpn_ip ?? '-' }}</strong>.
                </div>
            @elseif($connUnstable)
                <div class="mf-alert mf-alert-warn">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Tunnel <strong>tidak stabil</strong> — ping putus-nyambung. RADIUS NAS tetap menggunakan IP <strong>{{ $wgPeer->vpn_ip ?? '-' }}</strong>.
                </div>
            @else
                <div class="mf-alert mf-alert-danger">
                    <i class="fas fa-times-circle mr-2"></i>
                    Tunnel <strong>tidak terhubung</strong> (RTO). Periksa konfigurasi WireGuard di MikroTik.
                </div>
            @endif
            <div class="mf-wg-info">
                <div class="mf-wg-row">
                    <span class="mf-wg-key">Nama Peer</span>
                    <span class="mf-wg-val">{{ $wgPeer->name }}</span>
                </div>
                <div class="mf-wg-row">
                    <span class="mf-wg-key">IP Tunnel (NAS)</span>
                    <code class="mf-wg-val">{{ $wgPeer->vpn_ip ?? '-' }}</code>
                </div>
                <div class="mf-wg-row">
                    <span class="mf-wg-key">Status Peer</span>
                    <span class="mf-wg-val">{{ $wgPeer->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                </div>
                <div class="mf-wg-row">
                    <span class="mf-wg-key">Sync Terakhir</span>
                    <span class="mf-wg-val">{{ $wgPeer->last_synced_at ? $wgPeer->last_synced_at->format('d/m/Y H:i') : '-' }}</span>
                </div>
                @if($mikrotikConnection->last_ping_message)
                <div class="mf-wg-row">
                    <span class="mf-wg-key">Detail Ping</span>
                    <span class="mf-wg-val">{{ $mikrotikConnection->last_ping_message }}</span>
                </div>
                @endif
                <div class="mf-wg-row mf-wg-row-full">
                    <span class="mf-wg-key">Public Key</span>
                    <code class="mf-wg-val" style="word-break:break-all;font-size:.75rem;">{{ $wgPeer->public_key }}</code>
                </div>
            </div>
            <a href="{{ route('settings.wg') }}" class="mf-btn-back" style="margin-top:.25rem;">
                <i class="fas fa-cog mr-1"></i> Kelola di Pengaturan WireGuard
            </a>
        </div>
    </div>
    @endif

</div>{{-- /mf-page --}}

{{-- Script Generator Modal --}}
<div class="modal fade" id="scriptGeneratorModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(15,23,42,.18);">
            <div class="modal-header" style="background:#f8fbff;border-bottom:1px solid #d7e1ee;">
                <h5 class="modal-title" style="font-weight:700;font-size:.95rem;">
                    <i class="fas fa-code mr-2 text-muted"></i>RADIUS Script Generator
                </h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding:1.25rem;">
                @if($mikrotikConnection->wgPeer)
                <div class="mf-alert mf-alert-info mb-3">
                    <i class="fas fa-network-wired mr-2"></i>
                    Router ini menggunakan <strong>WireGuard Tunnel</strong>. Login address RADIUS menggunakan IP gateway tunnel: <strong>{{ config('wg.server_ip', '10.0.0.1') }}</strong>.
                </div>
                @endif
                <p style="font-size:.84rem;color:#5b6b83;margin-bottom:.75rem;">Salin script berikut ke terminal MikroTik untuk menyiapkan RADIUS (PPPoE/Hotspot/Login).</p>
                <textarea id="generated-script" class="mf-input" rows="11" readonly style="font-family:monospace;font-size:.78rem;height:auto;padding:.75rem;background:#f8fafc;"></textarea>
            </div>
            <div class="modal-footer" style="border-top:1px solid #d7e1ee;padding:.85rem 1.25rem;">
                <button type="button" class="mf-btn-cancel" data-dismiss="modal">Tutup</button>
                <button type="button" class="mf-btn-submit" id="copy-script-btn">
                    <i class="fas fa-copy mr-1"></i> Copy Script
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* ── Shared form styles (same as create) ─────────────────────────── */
.mf-page { display:flex; flex-direction:column; gap:1.1rem; }
.mf-page-header { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem; }
.mf-page-header-left { display:flex;align-items:center;gap:.85rem; }
.mf-page-icon { width:46px;height:46px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;color:#fff;flex-shrink:0;box-shadow:0 4px 14px rgba(0,0,0,.15); }
.mf-page-title { font-size:1.15rem;font-weight:700;color:var(--app-text,#0f172a);line-height:1.2; }
.mf-dim { color:var(--app-text-soft,#5b6b83);font-weight:500; }
.mf-page-sub { font-size:.8rem;color:var(--app-text-soft,#5b6b83);margin-top:.15rem; }
.mf-header-actions { display:flex;gap:.5rem;align-items:center;flex-wrap:wrap; }
.mf-btn-back { display:inline-flex;align-items:center;padding:.4rem .95rem;border-radius:9px;border:1px solid var(--app-border,#d7e1ee);background:#fff;color:var(--app-text,#0f172a);font-size:.82rem;font-weight:600;text-decoration:none;transition:background 140ms,transform 140ms; }
.mf-btn-back:hover { background:#f1f5ff;transform:translateY(-1px);color:var(--app-text,#0f172a);text-decoration:none; }
.mf-btn-script { display:inline-flex;align-items:center;padding:.4rem .9rem;border-radius:9px;border:none;background:linear-gradient(140deg,#334155,#64748b);color:#fff;font-size:.82rem;font-weight:600;cursor:pointer;transition:opacity 140ms,transform 140ms; }
.mf-btn-script:hover { opacity:.88;transform:translateY(-1px); }

/* ── Alerts ── */
.mf-alert { display:flex;align-items:flex-start;gap:.5rem;padding:.85rem 1rem;border-radius:12px;font-size:.84rem; }
.mf-alert-danger { background:#fef2f2;border:1px solid #fecaca;color:#991b1b; }
.mf-alert-info   { background:#eff6ff;border:1px solid #bfdbfe;color:#1e4d78; }
.mf-alert-warn   { background:#fffbeb;border:1px solid #fde68a;color:#92400e; }
.mf-alert-ok     { background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.3);color:#065f46; }

/* ── Credentials bar ── */
.mf-cred-bar { display:flex;align-items:center;flex-wrap:wrap;gap:.5rem;padding:.75rem 1rem;background:#f0f7ff;border:1px solid #c7dff7;border-radius:12px; }
.mf-cred-item { display:flex;align-items:center;gap:.5rem; }
.mf-cred-label { font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#1e4d78; }
.mf-cred-value { font-size:.8rem;padding:.15rem .45rem;border-radius:6px;background:#dbeafe;color:#1e3a5f; }
.mf-cred-sep { width:1px;height:22px;background:#c7dff7;margin:0 .25rem; }

/* ── Sync btn ── */
.mf-btn-sync { display:inline-flex;align-items:center;height:30px;padding:0 .75rem;border-radius:8px;border:none;cursor:pointer;font-size:.78rem;font-weight:600;background:linear-gradient(140deg,#b45309,#f59e0b);color:#fff;flex-shrink:0;transition:opacity 140ms; }
.mf-btn-sync:hover { opacity:.88; }

/* ── Grid & section ── */
.mf-grid { display:flex;flex-direction:column;gap:1rem; }
.mf-section { background:var(--app-surface,#fff);border:1px solid var(--app-border,#d7e1ee);border-radius:16px;box-shadow:0 4px 16px rgba(15,23,42,.05);overflow:hidden; }
.mf-section-header { display:flex;align-items:center;gap:.75rem;padding:.8rem 1.25rem;background:#f8fbff;border-bottom:1px solid var(--app-border,#d7e1ee); }
.mf-section-icon { width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.9rem;color:#fff;flex-shrink:0; }
.mf-section-title { font-size:.9rem;font-weight:700;color:var(--app-text,#0f172a); }
.mf-section-body { padding:1.1rem 1.25rem;display:flex;flex-direction:column;gap:.85rem; }
.mf-section-desc { font-size:.82rem;color:var(--app-text-soft,#5b6b83);margin:0; }

/* ── Rows ── */
.mf-row { display:grid;grid-template-columns:repeat(2,1fr);gap:.85rem; }
.mf-row-3 { grid-template-columns:repeat(3,1fr); }
.mf-row-4 { grid-template-columns:repeat(2,1fr); }
@media (min-width:992px) { .mf-row-3 { grid-template-columns:repeat(3,1fr); } .mf-row-4 { grid-template-columns:repeat(4,1fr); } }
@media (max-width:767px) { .mf-row,.mf-row-3,.mf-row-4 { grid-template-columns:1fr; } }
.mf-field { display:flex;flex-direction:column;gap:.3rem; }
.mf-field-auto { justify-content:flex-end; }

/* ── Labels & inputs ── */
.mf-label { font-size:.77rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--app-text-soft,#5b6b83);display:flex;align-items:center;gap:.4rem; }
.mf-req { color:#ef4444;font-size:.85em; }
.mf-opt { font-size:.7rem;font-weight:600;border-radius:20px;padding:.1rem .45rem;background:rgba(100,116,139,.1);color:#64748b;text-transform:none;letter-spacing:0; }
.mf-warn-chip { font-size:.68rem;font-weight:600;border-radius:20px;padding:.15rem .5rem;background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.3);color:#92400e;text-transform:none;letter-spacing:0;cursor:default; }
.mf-hint { font-size:.73rem;color:var(--app-text-soft,#5b6b83); }
.mf-input { height:38px;border-radius:9px;border:1px solid var(--app-border,#d7e1ee);padding:0 .75rem;font-size:.85rem;color:var(--app-text,#0f172a);background:#fff;outline:none;width:100%;transition:border-color 150ms,box-shadow 150ms; }
select.mf-input { appearance:auto; }
textarea.mf-input { height:auto;padding:.5rem .75rem;resize:vertical; }
.mf-input:focus { border-color:#8fb5df;box-shadow:0 0 0 3px rgba(19,103,164,.12); }
.mf-input-error { border-color:#f43f5e !important; }
.mf-feedback { font-size:.76rem;color:#dc2626;margin-top:.1rem; }

/* ── Switch ── */
.mf-switch { display:inline-flex;align-items:center;gap:.6rem;cursor:pointer;user-select:none;height:38px; }
.mf-switch input { display:none; }
.mf-switch-track { width:42px;height:24px;border-radius:99px;flex-shrink:0;background:#d1d5db;transition:background 200ms;position:relative; }
.mf-switch-track::after { content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:transform 200ms; }
.mf-switch input:checked ~ .mf-switch-track { background:linear-gradient(140deg,#0369a1,#0ea5e9); }
.mf-switch input:checked ~ .mf-switch-track::after { transform:translateX(18px); }
.mf-switch-label { font-size:.84rem;font-weight:600;color:var(--app-text,#0f172a); }

/* ── Test bar ── */
.mf-test-bar { display:flex;align-items:center;flex-wrap:wrap;gap:.75rem;margin-top:.25rem; }
.mf-btn-test { display:inline-flex;align-items:center;height:36px;padding:0 1rem;border-radius:9px;border:none;background:linear-gradient(140deg,#334155,#64748b);color:#fff;font-size:.82rem;font-weight:600;cursor:pointer;transition:opacity 140ms,transform 140ms; }
.mf-btn-test:hover { opacity:.88;transform:translateY(-1px); }
.mf-test-result { padding:.4rem .85rem;border-radius:9px;font-size:.81rem;font-weight:500; }
.mf-test-result.ok   { background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:#065f46; }
.mf-test-result.fail { background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#991b1b; }
.mf-test-result.info { background:#eff6ff;border:1px solid #bfdbfe;color:#1e4d78; }

/* ── Status chips ── */
.mf-status-chip { display:inline-flex;align-items:center;padding:.22rem .65rem;border-radius:20px;font-size:.72rem;font-weight:600; }
.mf-status-done    { background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:#065f46; }
.mf-status-ready   { background:rgba(14,165,233,.12);border:1px solid rgba(14,165,233,.3);color:#0369a1; }
.mf-status-pending { background:rgba(100,116,139,.12);border:1px solid rgba(100,116,139,.3);color:#334155; }
.mf-status-warn    { background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);color:#92400e; }
.mf-status-fail    { background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#991b1b; }

/* ── Reset isolir btn ── */
.mf-btn-reset-isolir { display:inline-flex;align-items:center;height:30px;padding:0 .75rem;border-radius:8px;border:1px solid rgba(245,158,11,.4);background:#fff;color:#92400e;font-size:.78rem;font-weight:600;cursor:pointer;flex-shrink:0;transition:background 140ms; }
.mf-btn-reset-isolir:hover { background:rgba(245,158,11,.08); }

/* ── WireGuard info table ── */
.mf-wg-info { display:grid;grid-template-columns:1fr 1fr;gap:.5rem .75rem;background:#f8fbff;border:1px solid var(--app-border,#d7e1ee);border-radius:12px;padding:.85rem 1rem; }
@media (max-width:767px) { .mf-wg-info { grid-template-columns:1fr; } }
.mf-wg-row { display:flex;flex-direction:column;gap:.15rem; }
.mf-wg-row-full { grid-column:1/-1; }
.mf-wg-key { font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--app-text-soft,#5b6b83); }
.mf-wg-val { font-size:.84rem;color:var(--app-text,#0f172a); }
.mf-btn-wg-create { display:inline-flex;align-items:center;padding:.35rem .8rem;border-radius:9px;border:1px solid rgba(14,165,233,.4);background:rgba(14,165,233,.08);color:#0369a1;font-size:.8rem;font-weight:600;text-decoration:none;flex-shrink:0;transition:background 140ms; }
.mf-btn-wg-create:hover { background:rgba(14,165,233,.15);color:#0369a1;text-decoration:none; }

/* ── Footer ── */
.mf-footer { display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;padding:1rem 1.25rem;background:var(--app-surface,#fff);border:1px solid var(--app-border,#d7e1ee);border-radius:14px; }
.mf-btn-cancel { font-size:.84rem;font-weight:600;color:var(--app-text-soft,#5b6b83);text-decoration:none;padding:.4rem .75rem;transition:color 140ms; }
.mf-btn-cancel:hover { color:var(--app-text,#0f172a);text-decoration:none; }
.mf-btn-submit { display:inline-flex;align-items:center;height:38px;padding:0 1.4rem;border-radius:10px;border:none;background:linear-gradient(140deg,#0369a1,#0ea5e9);color:#fff;font-size:.88rem;font-weight:700;cursor:pointer;box-shadow:0 3px 12px rgba(14,165,233,.25);transition:opacity 140ms,transform 140ms; }
.mf-btn-submit:hover { opacity:.9;transform:translateY(-1px); }
</style>

<script>
(function () {
    // ── Credentials ──────────────────────────────────────────────
    function generateCredentials() {
        const u = document.querySelector('input[name="username"]');
        const p = document.querySelector('input[name="password"]');
        const s = document.querySelector('input[name="radius_secret"]');
        if (document.getElementById('generated-username')) document.getElementById('generated-username').textContent = u.value;
        if (document.getElementById('generated-secret'))   document.getElementById('generated-secret').textContent   = p.value;
    }

    // ── SSL toggle ───────────────────────────────────────────────
    const sslToggle = document.getElementById('use_ssl');
    const sslGroup  = document.getElementById('ssl-port-group');
    const apiPortEl = document.querySelector('input[name="api_port"]');
    const apiSslEl  = document.getElementById('api_ssl_port');
    const portWarn  = document.getElementById('api-port-warning');

    function toggleSsl() { if (sslGroup) sslGroup.style.display = sslToggle.checked ? '' : 'none'; checkPortWarning(); }
    function checkPortWarning() {
        const port    = parseInt(apiPortEl ? apiPortEl.value : 0, 10);
        const sslPort = parseInt(apiSslEl  ? apiSslEl.value  : 0, 10);
        const show    = port === 8728 || (sslToggle && sslToggle.checked && sslPort === 8729);
        if (portWarn) portWarn.style.display = show ? '' : 'none';
    }
    if (sslToggle) sslToggle.addEventListener('change', toggleSsl);
    if (apiPortEl) apiPortEl.addEventListener('input', checkPortWarning);
    if (apiSslEl)  apiSslEl.addEventListener('input', checkPortWarning);

    // ── Script builder ───────────────────────────────────────────
    function escR(v) { return String(v).replace(/(["\\])/g, '\\$1'); }
    function buildScript() {
        const host      = @json($radiusHost);
        const apiUser   = document.querySelector('input[name="username"]').value;
        const apiPass   = document.querySelector('input[name="password"]').value;
        const secret    = document.querySelector('input[name="radius_secret"]').value || apiPass;
        const authPort  = document.querySelector('input[name="auth_port"]').value || 1812;
        const acctPort  = document.querySelector('input[name="acct_port"]').value  || 1813;
        const apiPort   = parseInt(document.querySelector('input[name="api_port"]').value, 10) || 8728;
        const apiSslPort= parseInt(apiSslEl ? apiSslEl.value : 8729, 10) || 8729;
        const lines = [];
        if (apiPort !== 8728 || apiSslPort !== 8729) {
            lines.push('# --- Ganti port API (keamanan) ---');
            if (apiPort !== 8728)    lines.push(`/ip service set api port=${apiPort}`);
            if (apiSslPort !== 8729) lines.push(`/ip service set api-ssl port=${apiSslPort}`);
            lines.push('');
        }
        lines.push(
            '# --- Konfigurasi user API & RADIUS ---',
            '/radius remove [find comment="added by TMDRadius"]',
            '/user remove [find comment="user for TMDRadius authentication"]',
            '/user group remove [find comment="group for TMDRadius authentication"]',
            `/user group add name="TMDRadius.group" policy=read,write,api,test,policy,sensitive comment="group for TMDRadius authentication"`,
            `/user add name="${escR(apiUser)}" group="TMDRadius.group" password="${escR(apiPass)}" comment="user for TMDRadius authentication"`,
            `/radius add authentication-port=${authPort} accounting-port=${acctPort} timeout=2s comment="added by TMDRadius" service=ppp,hotspot,login address="${host}" secret="${escR(secret)}"`,
            `/ip hotspot profile set use-radius=yes radius-accounting=yes radius-interim-update="00:10:00" nas-port-type="wireless-802.11" [find name!=""]`,
            `/ppp aaa set use-radius=yes accounting=yes interim-update="00:10:00"`,
            `/radius incoming set accept=yes port=3799`
        );
        const el = document.getElementById('generated-script');
        if (el) el.value = lines.join('\n');
    }

    // ── Test connection ──────────────────────────────────────────
    const testBtn    = document.getElementById('test-connection-btn');
    const testResult = document.getElementById('test-connection-result');
    if (testBtn) {
        testBtn.addEventListener('click', function () {
            const host      = document.querySelector('input[name="host"]').value;
            const apiPort   = apiPortEl ? apiPortEl.value : 8728;
            const apiSslPort= apiSslEl  ? apiSslEl.value  : 8729;
            const useSsl    = sslToggle ? sslToggle.checked : false;
            const timeout   = document.querySelector('input[name="api_timeout"]').value || 10;
            testResult.className = 'mf-test-result info';
            testResult.classList.remove('d-none');
            testResult.textContent = 'Menguji koneksi...';
            fetch('{{ route("mikrotik-connections.test") }}', {
                method: 'POST',
                headers: { 'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json' },
                body: JSON.stringify({ host, api_timeout: timeout, api_port: apiPort, api_ssl_port: apiSslPort, use_ssl: useSsl }),
            }).then(async function (r) {
                const d = await r.json();
                if (r.ok && d.success) {
                    testResult.className = 'mf-test-result ok';
                    testResult.innerHTML = '<i class="fas fa-check-circle mr-1"></i>' + d.message;
                } else {
                    testResult.className = 'mf-test-result fail';
                    testResult.innerHTML = '<i class="fas fa-times-circle mr-1"></i>' + (d.message || 'Koneksi gagal.') + (d.port_open === false ? ' Port API tertutup.' : '');
                }
            }).catch(function () {
                testResult.className = 'mf-test-result fail';
                testResult.innerHTML = '<i class="fas fa-times-circle mr-1"></i>Gagal menguji koneksi.';
            });
        });
    }

    // ── Script generator modal ───────────────────────────────────
    var scriptBtn = document.getElementById('script-generator-btn');
    if (scriptBtn) {
        scriptBtn.addEventListener('click', function () { generateCredentials(); buildScript(); });
    }
    var modal = document.getElementById('scriptGeneratorModal');
    if (modal) {
        modal.addEventListener('show.bs.modal', function () { generateCredentials(); buildScript(); });
    }

    // ── Copy script ──────────────────────────────────────────────
    document.getElementById('copy-script-btn')?.addEventListener('click', function () {
        const ta = document.getElementById('generated-script');
        ta.select(); ta.setSelectionRange(0, 99999);
        document.execCommand('copy');
        this.innerHTML = '<i class="fas fa-check mr-1"></i> Tersalin!';
        setTimeout(() => { this.innerHTML = '<i class="fas fa-copy mr-1"></i> Copy Script'; }, 2000);
    });

    // ── Sync RADIUS ──────────────────────────────────────────────
    var syncBtn = document.getElementById('sync-radius-clients-btn');
    if (syncBtn) {
        syncBtn.addEventListener('click', function () {
            var btn = this, orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Menyinkron…';
            fetch('{{ route("mikrotik-connections.radius-sync-clients") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json','X-Requested-With':'XMLHttpRequest' },
            }).then(r => r.json()).then(function (d) {
                btn.disabled = false;
                if (d.error) { btn.innerHTML = orig; alert('Sync gagal: ' + d.error); return; }
                var al = document.getElementById('radius-mismatch-alert');
                if (al) al.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-check mr-1"></i>Tersinkron';
            }).catch(function () { btn.disabled = false; btn.innerHTML = orig; alert('Gagal menghubungi server.'); });
        });
    }

    // ── Ping WG now ──────────────────────────────────────────────
    var pingBtn = document.getElementById('wg-ping-now-btn');
    if (pingBtn) {
        pingBtn.addEventListener('click', function () {
            var btn = this, orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Mengecek…';
            fetch('{{ route("mikrotik-connections.ping-now", $mikrotikConnection) }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json','X-Requested-With':'XMLHttpRequest' },
            }).then(r => r.json()).then(function (d) {
                btn.disabled = false; btn.innerHTML = orig;
                if (d.error) { alert('Ping gagal: ' + d.error); return; }
                window.location.reload();
            }).catch(function () { btn.disabled = false; btn.innerHTML = orig; alert('Gagal menghubungi server.'); });
        });
    }

    // ── Init ─────────────────────────────────────────────────────
    function init() { generateCredentials(); buildScript(); checkPortWarning(); }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
</script>
@endsection
