@php
    $currentUser   = auth()->user();
    $canManage     = $currentUser->isSuperAdmin() || in_array($currentUser->role, ['administrator', 'noc', 'it_support', 'teknisi'], true);
    $canReboot     = $canManage || $currentUser->role === 'teknisi';
    $canWifi       = $canManage || $currentUser->role === 'teknisi';
    $isTeknisi     = $currentUser->role === 'teknisi';
    $cpeDevice     = $pppUser->cpeDevice ?? null;
    $wifiNetworks  = $cpeDevice?->cached_params['wifi_networks'] ?? [];
    $wanConns      = $cpeDevice?->cached_params['wan_connections'] ?? [];
    $isIgd         = ($cpeDevice?->param_profile ?? 'igd') === 'igd';
@endphp

<div id="cpe-panel">
    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h5 class="mb-0"><i class="fas fa-router mr-2 text-primary"></i>Manajemen Perangkat CPE</h5>
            <small class="text-muted">Kelola perangkat TR-069 (modem/ONU) pelanggan melalui GenieACS</small>
        </div>
        <div>
            @if($canManage)
                <button class="btn btn-sm btn-info mr-1" id="btn-cpe-traffic" data-ppp-user-id="{{ $pppUser->id }}">
                    <i class="fas fa-tachometer-alt mr-1"></i> Cek Trafik
                </button>
            @endif
            @if($canManage)
                <button class="btn btn-sm btn-outline-primary" id="btn-cpe-sync" data-ppp-user-id="{{ $pppUser->id }}">
                    <i class="fas fa-search mr-1"></i> Sinkron GenieACS
                </button>
            @endif
            @if($cpeDevice)
                <button class="btn btn-sm btn-outline-secondary ml-1" id="btn-cpe-refresh" data-ppp-user-id="{{ $pppUser->id }}">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh Info
                </button>
            @endif
        </div>
    </div>

    @if($canManage)
    <div id="cpe-traffic-panel" class="border rounded p-2 mb-3 small" style="display:none;">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <p class="font-weight-bold mb-0"><i class="fas fa-chart-line mr-1"></i>Monitor Trafik PPPoE</p>
            <span id="cpe-traffic-queue" class="text-muted text-monospace" style="font-size:0.8em;"></span>
        </div>
        <div id="cpe-traffic-offline" class="text-center text-muted py-2" style="display:none;">
            <i class="fas fa-times-circle text-danger mr-1"></i> Queue tidak ditemukan di MikroTik
        </div>
        <div id="cpe-traffic-data" style="display:none;">
            <div class="d-flex justify-content-between mb-1">
                <span><i class="fas fa-arrow-down text-success mr-1"></i><span class="text-muted">RX:</span> <span id="cpe-traffic-rx" class="font-weight-bold text-success">-</span></span>
                <span><i class="fas fa-arrow-up text-primary mr-1"></i><span class="text-muted">TX:</span> <span id="cpe-traffic-tx" class="font-weight-bold text-primary">-</span></span>
                <span><span class="text-muted">Total DL:</span> <span id="cpe-traffic-bytes-in">-</span></span>
                <span><span class="text-muted">Total UL:</span> <span id="cpe-traffic-bytes-out">-</span></span>
            </div>
            <canvas id="cpe-traffic-chart" height="80"></canvas>
        </div>
        <div id="cpe-traffic-loading" class="text-center py-2">
            <i class="fas fa-spinner fa-spin"></i> Mengambil data...
        </div>
    </div>
    @endif

    @if(! $cpeDevice)
        <div id="cpe-not-linked" class="alert alert-info">
            <i class="fas fa-info-circle mr-2"></i>
            Perangkat belum terhubung ke GenieACS. Klik <strong>Sinkron GenieACS</strong> untuk mencari perangkat berdasarkan username PPPoE <code>{{ $pppUser->username }}</code>.
        </div>
    @else
        <div id="cpe-linked-section">

            {{-- Row 1: Info + Quick Actions --}}
            <div class="row mb-3">
                {{-- Device Info --}}
                <div class="col-md-5">
                    <div class="card card-outline card-primary h-100">
                        <div class="card-header py-2"><h6 class="mb-0">Informasi Perangkat</h6></div>
                        <div class="card-body p-2">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted" style="width:42%">Status PPPoE</td>
                                    <td id="cpe-pppoe-status">
                                        @php
                                            $pppoeSession = \App\Models\RadiusAccount::where('username', $pppUser->username)
                                                ->where('is_active', true)
                                                ->first(['ipv4_address']);
                                        @endphp
                                        @if($pppoeSession)
                                            <span class="badge badge-success">Online</span>
                                            @if($pppoeSession->ipv4_address)
                                                <span class="text-muted small ml-1">· {{ $pppoeSession->ipv4_address }}</span>
                                            @endif
                                        @else
                                            <span class="badge badge-danger">Offline</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Status TR-069</td>
                                    <td id="cpe-status">
                                        @php $lastSeen = $cpeDevice->last_seen_at?->diffForHumans() ?? '-'; @endphp
                                        @if($cpeDevice->status === 'online')
                                            <span class="badge badge-success">Online</span>
                                            <span class="text-muted small ml-1">· {{ $lastSeen }}</span>
                                        @elseif($cpeDevice->status === 'offline')
                                            <span class="badge badge-danger">Offline</span>
                                            <span class="text-muted small ml-1">· {{ $lastSeen }}</span>
                                        @else
                                            <span class="badge badge-secondary">Tidak Diketahui</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr><td class="text-muted">Pabrikan</td><td id="cpe-manufacturer">{{ $cpeDevice->manufacturer ?? '-' }}</td></tr>
                                <tr><td class="text-muted">Model</td><td id="cpe-model">{{ $cpeDevice->model ?? '-' }}</td></tr>
                                <tr><td class="text-muted">Firmware</td><td id="cpe-firmware">{{ $cpeDevice->firmware_version ?? '-' }}</td></tr>
                                <tr><td class="text-muted">Serial</td><td id="cpe-serial">{{ $cpeDevice->serial_number ?? '-' }}</td></tr>
                                <tr>
                                    <td class="text-muted">MAC Address</td>
                                    <td>
                                        @if($canManage)
                                        <div class="d-flex align-items-center" id="cpe-mac-display">
                                            <span class="text-monospace" id="cpe-mac-val">{{ $cpeDevice->mac_address ?? '-' }}</span>
                                            <a href="#" class="ml-2 text-muted small" id="cpe-mac-edit-btn" title="Edit MAC"><i class="fas fa-pencil-alt"></i></a>
                                        </div>
                                        <div class="d-none" id="cpe-mac-form">
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control form-control-sm text-monospace" id="cpe-mac-input"
                                                    placeholder="aa:bb:cc:dd:ee:ff"
                                                    value="{{ $cpeDevice->mac_address ?? '' }}"
                                                    data-ppp-user-id="{{ $pppUser->id }}">
                                                <div class="input-group-append">
                                                    <button class="btn btn-primary btn-sm" id="cpe-mac-save-btn"><i class="fas fa-check"></i></button>
                                                    <button class="btn btn-secondary btn-sm" id="cpe-mac-cancel-btn"><i class="fas fa-times"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                        @else
                                            <span class="text-monospace">{{ $cpeDevice->mac_address ?? '-' }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Device ID</td>
                                    <td><small class="text-monospace" id="cpe-device-id">{{ $cpeDevice->genieacs_device_id ?? '-' }}</small></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Redaman OLT</td>
                                    <td id="cpe-olt-signal">
                                        @php
                                            // Priority 1: manual link
                                            $oltOptic = $cpeDevice->olt_onu_optic_id
                                                ? \App\Models\OltOnuOptic::find($cpeDevice->olt_onu_optic_id)
                                                : null;
                                            // Priority 2: MAC auto-match
                                            if (! $oltOptic) {
                                                $wanMac = $cpeDevice->cached_params['wan_mac'] ?? null;
                                                if ($wanMac) {
                                                    $oltOptic = \App\Models\OltOnuOptic::query()
                                                        ->where('owner_id', $pppUser->owner_id)
                                                        ->whereNotNull('serial_number')
                                                        ->get(['id','serial_number','rx_onu_dbm','distance_m','status','onu_name'])
                                                        ->first(fn($r) => strtoupper(preg_replace('/[^0-9A-Fa-f]/','', $r->serial_number)) === $wanMac);
                                                }
                                            }
                                            $isManualLink = $cpeDevice->olt_onu_optic_id !== null;
                                        @endphp
                                        @if($oltOptic)
                                            @php
                                                $rxDbm = $oltOptic->rx_onu_dbm !== null ? number_format((float)$oltOptic->rx_onu_dbm, 2) : null;
                                                $rxClass = $rxDbm !== null && (float)$rxDbm < -27 ? 'text-danger' : 'text-success';
                                                $distM = $oltOptic->distance_m;
                                            @endphp
                                            <a href="#" id="cpe-olt-signal-link" data-onu-optic-id="{{ $oltOptic->id }}" data-ppp-user-id="{{ $pppUser->id }}">
                                                @if($rxDbm !== null)
                                                    <span class="{{ $rxClass }}">{{ $rxDbm }} dBm</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                                @if($distM !== null)
                                                    <span class="text-muted small ml-1">· {{ number_format($distM) }} m</span>
                                                @endif
                                                <i class="fas fa-chart-line ml-1 text-muted small"></i>
                                            </a>
                                            @if($isManualLink)
                                                <span class="badge badge-secondary ml-1" title="Link manual: {{ $oltOptic->onu_name }}">manual</span>
                                                <a href="#" class="ml-1 text-muted small" id="cpe-olt-unlink-btn" data-ppp-user-id="{{ $pppUser->id }}" title="Hapus link manual"><i class="fas fa-unlink"></i></a>
                                            @else
                                                <a href="#" class="ml-1 text-muted small" data-toggle="modal" data-target="#cpe-olt-link-modal" title="Link ONU manual"><i class="fas fa-link"></i></a>
                                            @endif
                                        @else
                                            <span id="cpe-olt-signal-val" class="text-muted">-</span>
                                            <a href="#" class="ml-1 text-muted small" data-toggle="modal" data-target="#cpe-olt-link-modal" title="Link ONU manual"><i class="fas fa-link"></i></a>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Quick Actions --}}
                <div class="col-md-7">
                    <div class="card card-outline card-warning h-100">
                        <div class="card-header py-2"><h6 class="mb-0">Aksi Cepat</h6></div>
                        <div class="card-body p-2">
                            @if($canReboot)
                            <button class="btn btn-warning btn-sm btn-block mb-2" id="btn-cpe-reboot" data-ppp-user-id="{{ $pppUser->id }}">
                                <i class="fas fa-power-off mr-1"></i> Reboot Perangkat
                            </button>
                            @endif

                            @if($canManage)
                            {{-- PPPoE Info (read-only) --}}
                            <div class="border rounded p-2 mb-2">
                                <p class="font-weight-bold mb-1 small"><i class="fas fa-key mr-1"></i>Info PPPoE Modem</p>

                                @php
                                    $wanConns     = $cpeDevice->cached_params['wan_connections'] ?? [];
                                    $pppWan       = collect($wanConns)->firstWhere('connection_type', 'PPPoE') ?? collect($wanConns)->first();
                                    $modemPppUser = $pppWan['username'] ?? null;
                                    $modemStatus  = $pppWan['status'] ?? null;
                                    $modemIp      = $pppWan['external_ip'] ?? null;
                                @endphp
                                <div class="bg-light rounded p-2 mb-1 small">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Serial:</span>
                                        <span class="font-weight-bold">{{ $cpeDevice->serial_number ?? '-' }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Model:</span>
                                        <span>{{ $cpeDevice->model ?? '-' }}</span>
                                    </div>
                                    @if($modemIp && $modemIp !== '0.0.0.0')
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">IP WAN:</span>
                                        <span>{{ $modemIp }}</span>
                                    </div>
                                    @endif
                                    @if($cpeDevice->status === 'online')
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Status PPPoE:</span>
                                        @if($modemStatus === 'Connected')
                                            <span class="badge badge-success badge-sm">Connected</span>
                                        @elseif($modemStatus)
                                            <span class="badge badge-secondary badge-sm">{{ $modemStatus }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </div>
                                    @endif
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Username di modem:</span>
                                        @if($modemPppUser)
                                            <span class="text-monospace">{{ $modemPppUser }}</span>
                                        @else
                                            <span class="text-danger">(kosong)</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="alert alert-info py-1 px-2 mb-0 small">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Ubah username/password PPPoE melalui tab <strong>Edit Pelanggan → Info Pelanggan</strong>. Perubahan akan otomatis dikirim ke modem.
                                </div>
                            </div>

                            <button class="btn btn-outline-danger btn-sm btn-block" id="btn-cpe-unlink" data-ppp-user-id="{{ $pppUser->id }}">
                                <i class="fas fa-unlink mr-1"></i> Lepaskan Tautan Perangkat
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @if($canWifi)
            {{-- Row 2: Multi-SSID WiFi --}}
            <div class="card card-outline card-success mb-3" id="cpe-wifi-section">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0"><i class="fas fa-wifi mr-1 text-success"></i> Jaringan WiFi</h6>
                    <small class="text-muted" id="cpe-wifi-count">
                        {{ count($wifiNetworks) > 0 ? count($wifiNetworks).' jaringan terdeteksi' : 'Klik Refresh Info untuk memuat' }}
                    </small>
                </div>
                <div class="card-body p-2" id="cpe-wifi-accordion">
                    @if(count($wifiNetworks) > 0)
                        @foreach($wifiNetworks as $wifi)
                        @php $wIdx = $wifi['index']; @endphp
                        <div class="border rounded mb-2 cpe-wifi-card" id="cpe-wifi-card-{{ $wIdx }}">
                            <div class="d-flex align-items-center justify-content-between px-3 py-2"
                                 style="cursor:pointer" data-toggle="collapse" data-target="#cpe-wifi-body-{{ $wIdx }}">
                                <div>
                                    <strong class="mr-2">
                                        <i class="fas fa-wifi mr-1 {{ $wifi['enabled'] ? 'text-success' : 'text-muted' }}"></i>
                                        {{ $wifi['ssid'] ?: '(SSID kosong)' }}
                                    </strong>
                                    <span class="badge {{ $wifi['band'] === '5GHz' ? 'badge-info' : 'badge-secondary' }} badge-sm">{{ $wifi['band'] }}</span>
                                    <span class="badge {{ $wifi['enabled'] ? 'badge-success' : 'badge-danger' }} badge-sm ml-1 cpe-wifi-status-{{ $wIdx }}">
                                        {{ $wifi['enabled'] ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>
                                <i class="fas fa-chevron-down text-muted small"></i>
                            </div>
                            <div class="collapse px-3 pb-3" id="cpe-wifi-body-{{ $wIdx }}">
                                <div class="row">
                                    <div class="col-sm-5">
                                        <div class="form-group mb-1">
                                            <label class="small mb-0">Nama WiFi (SSID)</label>
                                            <input type="text" class="form-control form-control-sm cpe-wifi-ssid-val"
                                                   data-wlan-idx="{{ $wIdx }}" value="{{ $wifi['ssid'] }}" maxlength="32">
                                        </div>
                                        <div class="form-group mb-1">
                                            <label class="small mb-0">Password (min 8 karakter)</label>
                                            <input type="text" class="form-control form-control-sm cpe-wifi-pass-val"
                                                   data-wlan-idx="{{ $wIdx }}" value="{{ $wifi['password'] }}" maxlength="63">
                                        </div>
                                    </div>
                                    <div class="col-sm-4">
                                        <div class="form-group mb-1">
                                            <label class="small mb-0">Channel</label>
                                            @if(!$isTeknisi)
                                            <select class="form-control form-control-sm cpe-wifi-channel-val"
                                                    data-wlan-idx="{{ $wIdx }}">
                                                @if($wifi['band'] === '5GHz')
                                                    <option value="0" {{ (int)($wifi['channel'] ?? 0) === 0 ? 'selected' : '' }}>Auto</option>
                                                    @foreach([36,40,44,48,52,56,60,64,100,104,108,112,116,120,124,128,132,136,140,149,153,157,161,165] as $ch)
                                                    <option value="{{ $ch }}" {{ (int)($wifi['channel'] ?? 0) === $ch ? 'selected' : '' }}>{{ $ch }}</option>
                                                    @endforeach
                                                @else
                                                    <option value="0" {{ (int)($wifi['channel'] ?? 0) === 0 ? 'selected' : '' }}>Auto</option>
                                                    @foreach([1,2,3,4,5,6,7,8,9,10,11,12,13] as $ch)
                                                    <option value="{{ $ch }}" {{ (int)($wifi['channel'] ?? 0) === $ch ? 'selected' : '' }}>{{ $ch }}</option>
                                                    @endforeach
                                                @endif
                                            </select>
                                            @else
                                            <input type="text" class="form-control form-control-sm"
                                                   value="{{ $wifi['channel'] ?? '-' }}" readonly>
                                            @endif
                                        </div>
                                        <div class="form-group mb-1">
                                            <label class="small mb-0">Standar</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $wifi['standard'] ?? '-' }}" readonly>
                                        </div>
                                    </div>
                                    <div class="col-sm-3 d-flex flex-column justify-content-end">
                                        @if(! $isTeknisi)
                                        <div class="custom-control custom-switch mb-2">
                                            <input type="checkbox" class="custom-control-input cpe-wifi-enable-val"
                                                   id="cpe-wifi-en-{{ $wIdx }}" data-wlan-idx="{{ $wIdx }}"
                                                   {{ $wifi['enabled'] ? 'checked' : '' }}>
                                            <label class="custom-control-label small" for="cpe-wifi-en-{{ $wIdx }}">Aktifkan</label>
                                        </div>
                                        @endif
                                        <button class="btn btn-success btn-sm btn-block btn-cpe-wifi-save"
                                                data-ppp-user-id="{{ $pppUser->id }}" data-wlan-idx="{{ $wIdx }}">
                                            <i class="fas fa-save mr-1"></i> Simpan
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    @else
                        <div id="cpe-wifi-empty" class="text-muted small text-center py-2">
                            Data WiFi belum tersedia — klik <strong>Refresh Info</strong>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Row 3: WAN Connections --}}
            <div class="card card-outline card-secondary mb-2" id="cpe-wan-section">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0"><i class="fas fa-network-wired mr-1"></i> Koneksi WAN</h6>
                    <small class="text-muted" id="cpe-wan-count">
                        {{ count($wanConns) > 0 ? count($wanConns).' koneksi terdeteksi' : 'Klik Refresh Info untuk memuat' }}
                    </small>
                </div>
                <div class="card-body p-2" id="cpe-wan-accordion">
                    @if(count($wanConns) > 0)
                        @foreach($wanConns as $wan)
                        @php
                            $wKey    = $wan['key'];
                            $wStatus = $wan['status'] ?? 'Unknown';
                            $wName   = $wan['name'] ?? "WAN {$wKey}";
                        @endphp
                        <div class="border rounded mb-2 cpe-wan-card" id="cpe-wan-card-{{ str_replace('.','_',$wKey) }}">
                            <div class="d-flex align-items-center justify-content-between px-3 py-2"
                                 style="cursor:pointer" data-toggle="collapse" data-target="#cpe-wan-body-{{ str_replace('.','_',$wKey) }}">
                                <div>
                                    <strong class="mr-2">{{ $wName }}</strong>
                                    <span class="badge {{ $wStatus === 'Connected' ? 'badge-success' : 'badge-danger' }} badge-sm">{{ $wStatus }}</span>
                                    @if($wan['vlan_id'])
                                        <span class="badge badge-light border badge-sm ml-1">VLAN {{ $wan['vlan_id'] }}</span>
                                    @endif
                                    @if($wan['external_ip'] && $wan['external_ip'] !== '0.0.0.0')
                                        <span class="text-muted small ml-2">{{ $wan['external_ip'] }}</span>
                                    @endif
                                </div>
                                <i class="fas fa-chevron-down text-muted small"></i>
                            </div>
                            <div class="collapse px-3 pb-3" id="cpe-wan-body-{{ str_replace('.','_',$wKey) }}">
                                <div class="row">
                                    {{-- Kolom kiri: info + PPPoE --}}
                                    <div class="{{ $isIgd ? 'col-md-6' : 'col-md-12' }}">
                                        @if($isIgd)
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="form-group mb-1">
                                                    <label class="small mb-0">Tipe Koneksi</label>
                                                    <select class="form-control form-control-sm cpe-wan-conn-type"
                                                            data-wan-key="{{ $wKey }}">
                                                        <option value="IP_Routed" {{ ($wan['connection_type'] ?? '') === 'IP_Routed' ? 'selected' : '' }}>Routed (IP_Routed)</option>
                                                        <option value="PPPoE_Bridged" {{ ($wan['connection_type'] ?? '') === 'PPPoE_Bridged' ? 'selected' : '' }}>Bridge (PPPoE_Bridged)</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="form-group mb-1">
                                                    <label class="small mb-0">VLAN ID</label>
                                                    <input type="number" class="form-control form-control-sm cpe-wan-vlan"
                                                           data-wan-key="{{ $wKey }}" value="{{ $wan['vlan_id'] }}" min="1" max="4094">
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="form-group mb-1">
                                                    <label class="small mb-0">VLAN Priority (802.1p)</label>
                                                    <input type="number" class="form-control form-control-sm cpe-wan-vlan-prio"
                                                           data-wan-key="{{ $wKey }}" value="{{ $wan['vlan_prio'] ?? 0 }}" min="0" max="7">
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="form-group mb-1">
                                                    <label class="small mb-0">DNS Servers</label>
                                                    <input type="text" class="form-control form-control-sm cpe-wan-dns"
                                                           data-wan-key="{{ $wKey }}" value="{{ $wan['dns_servers'] }}" placeholder="8.8.8.8,8.8.4.4">
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                        @if($wan['ppp_idx'] !== null)
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="form-group mb-1">
                                                    <label class="small mb-0">Username PPPoE</label>
                                                    <input type="text" class="form-control form-control-sm cpe-wan-user"
                                                           data-wan-key="{{ $wKey }}" value="{{ $wan['username'] }}" maxlength="64">
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="form-group mb-1">
                                                    <label class="small mb-0">Password PPPoE</label>
                                                    <input type="text" class="form-control form-control-sm cpe-wan-pass"
                                                           data-wan-key="{{ $wKey }}" value="" placeholder="(tidak diubah)" maxlength="64">
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                        <div class="d-flex align-items-center justify-content-between mt-2">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input cpe-wan-enable"
                                                       id="cpe-wan-en-{{ str_replace('.','_',$wKey) }}" data-wan-key="{{ $wKey }}"
                                                       {{ $wan['enabled'] ? 'checked' : '' }}>
                                                <label class="custom-control-label small" for="cpe-wan-en-{{ str_replace('.','_',$wKey) }}">Aktifkan</label>
                                            </div>
                                            <button class="btn btn-primary btn-sm btn-cpe-wan-save"
                                                    data-ppp-user-id="{{ $pppUser->id }}" data-wan-key="{{ $wKey }}">
                                                <i class="fas fa-save mr-1"></i> Simpan
                                            </button>
                                        </div>
                                        <div class="mt-2 small text-muted">
                                            <span>Uptime: {{ $wan['uptime'] ? gmdate('H:i:s', (int)$wan['uptime']) : '-' }}</span>
                                            @if($isIgd)
                                            &nbsp;·&nbsp;
                                            <span>NAT: {{ $wan['nat_enabled'] ? 'Ya' : 'Tidak' }}</span>
                                            @if($wan['service_list'])
                                            &nbsp;·&nbsp;<span>Service: {{ $wan['service_list'] }}</span>
                                            @endif
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Kolom kanan: Port Binding (IGD only) --}}
                                    @if($isIgd)
                                    <div class="col-md-6">
                                        <label class="small mb-1">Port Binding (LAN Interface)</label>
                                        @php
                                            $boundIfaces = array_map('trim', array_filter(explode(',', $wan['lan_interface'] ?? '')));
                                            $base        = 'InternetGatewayDevice.LANDevice.1';
                                            // LAN ports 1-4
                                            $allEthPaths = [
                                                1 => "{$base}.LANEthernetInterfaceConfig.1",
                                                2 => "{$base}.LANEthernetInterfaceConfig.2",
                                                3 => "{$base}.LANEthernetInterfaceConfig.3",
                                                4 => "{$base}.LANEthernetInterfaceConfig.4",
                                            ];
                                            // All 8 SSID slots matching modem GUI (SSID1-8 → WLAN.1,2,3,4,5,6,7,8)
                                            // Odd indices = 2.4GHz, even indices = 5GHz on this modem
                                            $wifiNetworkMap = collect($wifiNetworks)->keyBy('index');
                                            $allSsidSlots = [1,2,3,4,5,6,7,8];
                                        @endphp
                                        <div class="border rounded p-2 mb-2">
                                            <div class="small font-weight-bold text-muted mb-1">LAN Port</div>
                                            <div class="d-flex flex-wrap">
                                            @foreach($allEthPaths as $ethNum => $ethPath)
                                            @php
                                                $cbId    = 'cb-wan-'.str_replace('.','_',$wKey).'-eth'.$ethNum;
                                                $checked = in_array($ethPath, $boundIfaces) ? 'checked' : '';
                                            @endphp
                                            <div class="custom-control custom-checkbox mr-3 mb-1">
                                                <input type="checkbox" class="custom-control-input cpe-wan-iface-cb"
                                                       id="{{ $cbId }}" data-wan-key="{{ $wKey }}" data-iface="{{ $ethPath }}" {{ $checked }}>
                                                <label class="custom-control-label small" for="{{ $cbId }}">LAN{{ $ethNum }}</label>
                                            </div>
                                            @endforeach
                                            </div>
                                            <div class="small font-weight-bold text-muted mt-1 mb-1">WiFi SSID</div>
                                            <div class="d-flex flex-wrap">
                                            @foreach($allSsidSlots as $ssidNum)
                                            @php
                                                $wlanPath  = "{$base}.WLANConfiguration.{$ssidNum}";
                                                $cbId2     = 'cb-wan-'.str_replace('.','_',$wKey).'-ssid'.$ssidNum;
                                                $checked2  = in_array($wlanPath, $boundIfaces) ? 'checked' : '';
                                                $wlanNet   = $wifiNetworkMap->get($ssidNum);
                                                // Label: SSID{n} with name if known and active
                                                $ssidLabel = 'SSID'.$ssidNum;
                                                if ($wlanNet && $wlanNet['ssid']) {
                                                    $ssidLabel .= ' ('.$wlanNet['ssid'].')';
                                                }
                                            @endphp
                                            <div class="custom-control custom-checkbox mr-3 mb-1">
                                                <input type="checkbox" class="custom-control-input cpe-wan-iface-cb"
                                                       id="{{ $cbId2 }}" data-wan-key="{{ $wKey }}" data-iface="{{ $wlanPath }}" {{ $checked2 }}>
                                                <label class="custom-control-label small" for="{{ $cbId2 }}">{{ $ssidLabel }}</label>
                                            </div>
                                            @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    @endif {{-- end @if($isIgd) kolom kanan --}}
                                </div>
                            </div>
                        </div>
                        @endforeach
                    @else
                        <div id="cpe-wan-empty" class="text-muted small text-center py-2">
                            Data WAN belum tersedia — klik <strong>Refresh Info</strong>
                        </div>
                    @endif
                </div>
            </div>
            @endif

        </div>{{-- #cpe-linked-section --}}
    @endif
</div>

{{-- Modal: Riwayat Redaman OLT --}}
<div class="modal fade" id="cpe-olt-history-modal" tabindex="-1" role="dialog" aria-labelledby="cpe-olt-history-label" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0" id="cpe-olt-history-label"><i class="fas fa-chart-line mr-2"></i>Riwayat Redaman OLT</h6>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-2">
                <div id="cpe-olt-history-loading" class="text-center py-3">
                    <i class="fas fa-spinner fa-spin mr-1"></i> Memuat...
                </div>
                <div id="cpe-olt-history-content" style="display:none;">
                    <table class="table table-sm table-bordered table-striped mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Waktu Poll</th>
                                <th>RX ONU (dBm)</th>
                                <th>Jarak (m)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="cpe-olt-history-tbody"></tbody>
                    </table>
                    <div id="cpe-olt-history-empty" class="text-center text-muted py-3" style="display:none;">
                        Belum ada data riwayat.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Link ONU Manual --}}
<div class="modal fade" id="cpe-olt-link-modal" tabindex="-1" role="dialog" aria-labelledby="cpe-olt-link-label" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0" id="cpe-olt-link-label"><i class="fas fa-link mr-2"></i>Link ONU ke Modem Ini</h6>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-2">
                    <input type="text" id="cpe-olt-link-search" class="form-control form-control-sm" placeholder="Cari nama ONU atau serial...">
                </div>
                <div id="cpe-olt-link-loading" class="text-center py-2" style="display:none;">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
                <div id="cpe-olt-link-list" style="max-height:300px;overflow-y:auto;">
                    <p class="text-muted small text-center py-2">Ketik untuk mencari ONU.</p>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
            </div>
        </div>
    </div>
</div>

{{-- JS dipush ke @push('scripts') di edit.blade.php agar jQuery sudah ready --}}
