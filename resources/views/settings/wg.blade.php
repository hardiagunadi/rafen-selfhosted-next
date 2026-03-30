@extends('layouts.admin')

@section('title', 'Pengaturan WireGuard')

@section('content')
    <div class="card mb-4">
        <div class="card-header">
            <h4 class="mb-0">Informasi Server WireGuard</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-2">
                        <strong>IP/Host Publik:</strong>
                        @if ($wg['host'] !== '')
                            <span class="text-dark">{{ $wg['host'] }}</span>
                        @elseif ($detectedIp !== null)
                            <span class="text-success font-weight-bold">{{ $detectedIp }}</span>
                            <span class="badge badge-warning ml-1" title="Set WG_HOST di .env untuk menetapkan permanen.">Auto-detect</span>
                        @else
                            <span class="text-danger">-</span>
                            <small class="text-muted ml-1">(set WG_HOST di .env)</small>
                        @endif
                    </div>
                    <div class="mb-2"><strong>Interface:</strong> {{ $wg['interface'] !== '' ? $wg['interface'] : 'wg0' }}</div>
                    <div class="mb-2"><strong>Listen Port:</strong> {{ $wg['listen_port'] !== '' ? $wg['listen_port'] : '51820' }} <span class="badge badge-secondary">UDP</span></div>
                    <div class="mb-2"><strong>Server Address:</strong> {{ $wg['server_address'] !== '' ? $wg['server_address'] : '-' }}</div>
                    <div class="mb-2"><strong>Server IP (tunnel):</strong> {{ $wg['server_ip'] !== '' ? $wg['server_ip'] : '-' }}</div>
                    <div class="mb-2">
                        <strong>Pool IP:</strong>
                        {{ $wg['pool_start'] !== '' ? $wg['pool_start'] : '-' }} –
                        {{ $wg['pool_end'] !== '' ? $wg['pool_end'] : '-' }}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-2">
                        <strong>Server Public Key:</strong>
                        @if ($wg['server_public_key'] !== '')
                            <code style="font-size:11px; word-break:break-all;">{{ $wg['server_public_key'] }}</code>
                            @if ($keyAutoDetected)
                                <span class="badge badge-warning ml-1" title="Key dibaca dari file, belum disimpan di .env">Auto-detect</span>
                            @endif
                        @else
                            <span class="text-danger">-</span>
                            <small class="text-muted ml-1">(WireGuard belum terinstall — jalankan install-wg.sh)</small>
                        @endif
                    </div>
                    @if ($keyAutoDetected)
                        <div class="alert alert-warning py-2 mb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <span>
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                Server keypair dibaca dari <code>/etc/wireguard/</code> — belum tersimpan di <code>.env</code>.
                            </span>
                            <form method="POST" action="{{ route('settings.wg.save-server-keys') }}" class="mb-0">
                                @csrf
                                <button type="submit" class="btn btn-warning btn-sm">
                                    <i class="fas fa-save mr-1"></i> Simpan ke .env
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
            @if ($wg['host'] === '' && $detectedIp !== null)
                <div class="alert alert-warning mb-0 mt-3 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <span>
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        IP publik <strong>{{ $detectedIp }}</strong> terdeteksi otomatis dari server.
                        Simpan ke <code>.env</code> agar permanen dan script generator berfungsi dengan benar.
                    </span>
                    @if(auth()->user()?->isAdmin())
                        <form method="POST" action="{{ route('settings.wg.save-host') }}" class="mb-0">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-sm">
                                <i class="fas fa-save mr-1"></i> Simpan WG_HOST ke .env
                            </button>
                        </form>
                    @endif
                </div>
            @endif
            <div class="alert alert-info mb-0 mt-3">
                <i class="fas fa-shield-alt mr-1"></i>
                WireGuard berfungsi sebagai <strong>tunnel privat</strong> agar server RAFEN dapat terhubung ke MikroTik.
                Setelah tunnel aktif, RADIUS NAS otomatis menggunakan <strong>IP tunnel</strong> (bukan IP publik router).
                Membutuhkan <strong>RouterOS v7.1+</strong>.
            </div>
        </div>
    </div>

    {{-- Card Scheduler --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Laravel Scheduler</h4>
            <span class="badge badge-success"><i class="fas fa-check mr-1"></i>Aktif (Systemd)</span>
        </div>
        <div class="card-body">
            <div class="alert alert-success py-2 mb-0">
                <i class="fas fa-check-circle mr-1"></i>
                Scheduler berjalan via <strong>systemd timer</strong> (<code>rafen-schedule.timer</code>).
                Ping router otomatis setiap <strong>5 menit</strong>.
            </div>
        </div>
    </div>

    <div class="card mb-4" id="tambah-peer">
        <div class="card-header">
            <h4 class="mb-0">Tambah WireGuard Peer</h4>
        </div>
        <form id="wg-store-form" action="{{ route('settings.wg.peers.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @php $preselectedRouterId = request()->query('router_id'); $preselectedRouterName = request()->query('router_name'); @endphp
                @if($preselectedRouterId && $preselectedRouterName)
                    <div class="alert alert-info py-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Membuat tunnel WireGuard untuk router <strong>{{ $preselectedRouterName }}</strong>.
                        Setelah disimpan, copy script ke MikroTik, lalu kembali ke halaman edit router untuk sync RADIUS.
                    </div>
                @endif
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Router MikroTik</label>
                        <small class="text-muted d-block mb-1">Pilih router yang akan dihubungkan via tunnel ini</small>
                        <select name="mikrotik_connection_id" class="form-control @error('mikrotik_connection_id') is-invalid @enderror">
                            <option value="">Tanpa Router (tunnel saja)</option>
                            @foreach($routers as $router)
                                <option value="{{ $router->id }}"
                                    @selected(old('mikrotik_connection_id', $preselectedRouterId) == $router->id)>{{ $router->name }}</option>
                            @endforeach
                        </select>
                        @error('mikrotik_connection_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Nama Peer</label>
                        <input type="text" name="name" value="{{ old('name', $preselectedRouterName) }}" class="form-control @error('name') is-invalid @enderror" required placeholder="Contoh: Router Kediri">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>IP VPN (opsional)</label>
                        <input type="text" name="vpn_ip" value="{{ old('vpn_ip') }}" class="form-control @error('vpn_ip') is-invalid @enderror" placeholder="auto jika kosong">
                        @error('vpn_ip')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                {{-- Info auto-generate keypair --}}
                <div class="alert alert-info py-2 mb-2 d-flex align-items-center justify-content-between">
                    <span>
                        <i class="fas fa-magic mr-1"></i>
                        Keypair akan di-<strong>generate otomatis</strong>. Script MikroTik sudah include
                        <code>private-key</code> — jalankan script, handshake langsung terjadi.
                    </span>
                    <a href="#" id="toggle-manual-keypair" class="ml-3 text-nowrap">
                        <small>Gunakan keypair sendiri</small>
                    </a>
                </div>

                {{-- Manual keypair fields — tersembunyi by default --}}
                <div id="manual-keypair-fields" class="form-row" style="display:none">
                    <div class="form-group col-md-6">
                        <label>Public Key</label>
                        <input type="text" name="public_key" value="{{ old('public_key') }}"
                               class="form-control @error('public_key') is-invalid @enderror"
                               placeholder="base64 public key WireGuard" style="font-family:monospace;font-size:12px;">
                        @error('public_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Private Key</label>
                        <input type="text" name="private_key" value="{{ old('private_key') }}"
                               class="form-control @error('private_key') is-invalid @enderror"
                               placeholder="base64 private key WireGuard" style="font-family:monospace;font-size:12px;">
                        @error('private_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Status</label>
                        <select name="is_active" class="form-control @error('is_active') is-invalid @enderror">
                            <option value="1" @selected(old('is_active', '1') == '1')>Aktif</option>
                            <option value="0" @selected(old('is_active') == '0')>Nonaktif</option>
                        </select>
                        @error('is_active')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <span class="text-muted">Keypair WireGuard di-generate otomatis jika public/private key dikosongkan.</span>
                <button type="submit" class="btn btn-primary" id="wg-store-btn">Simpan Peer</button>
            </div>
        </form>
    </div>

    <div id="wg-alert" style="display:none;" class="alert mb-3"></div>

    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Daftar WireGuard Peer</h4>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                    <tr>
                        <th>Router</th>
                        <th>Nama</th>
                        <th>IP VPN</th>
                        <th>Public Key</th>
                        <th>Status</th>
                        <th>Sync Terakhir</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                    </thead>
                    <tbody id="wg-peers-tbody">
                    @forelse($peers as $peer)
                        @php
                            $wgHost       = $wg['host'] !== '' ? $wg['host'] : ($detectedIp ?? '<IP/Host>');
                            $serverPubKey = $wg['server_public_key'] !== '' ? $wg['server_public_key'] : '<SERVER_PUBLIC_KEY>';
                            $serverIp     = $wg['server_ip'] !== '' ? $wg['server_ip'] : '10.0.0.1';
                            $listenPort   = $wg['listen_port'] !== '' ? $wg['listen_port'] : '51820';
                            $clientIp     = $peer->vpn_ip ?? '<CLIENT_IP>';
                            $clientPriv   = $peer->private_key ?? '<CLIENT_PRIVATE_KEY>';
                            $radiusSecret = $peer->mikrotikConnection?->radius_secret ?? '<RADIUS_SECRET>';
                            $peerName     = $peer->name;

                            $wgScript = implode("\n", [
                                '# ============================================================',
                                '# WireGuard Peer  : ' . $peerName,
                                '# RouterOS        : v7.1+',
                                '# IP Tunnel       : ' . $clientIp . '/24',
                                '# Server Public Key: ' . $serverPubKey,
                                '# ============================================================',
                                '',
                                '# --- Bersihkan konfigurasi WireGuard lama (jika ada) ---',
                                '/interface/wireguard/peers remove [find interface=wg-vpn]',
                                '/interface/wireguard remove [find name=wg-vpn]',
                                '/ip/address remove [find interface=wg-vpn]',
                                '',
                                '# --- Buat interface WireGuard ---',
                                '/interface/wireguard add name=wg-vpn private-key="' . $clientPriv . '" listen-port=13231 comment="RAFEN VPN"',
                                '',
                                '# --- Tambahkan peer (server) ---',
                                '/interface/wireguard/peers add \\',
                                '    interface=wg-vpn \\',
                                '    public-key="' . $serverPubKey . '" \\',
                                '    endpoint-address=' . $wgHost . ' \\',
                                '    endpoint-port=' . $listenPort . ' \\',
                                '    allowed-address=0.0.0.0/0 \\',
                                '    persistent-keepalive=25',
                                '',
                                '# --- Assign IP address pada interface tunnel ---',
                                '/ip/address add address=' . $clientIp . '/24 interface=wg-vpn',
                                '',
                                '# --- Verifikasi koneksi ---',
                                '/interface/wireguard/peers print',
                                '/ip/address print where interface=wg-vpn',
                            ]);

                            $radiusScript = implode("\n", [
                                '# ============================================================',
                                '# RADIUS via WireGuard untuk : ' . $peerName,
                                '# Server RADIUS      : ' . $serverIp . ' (IP tunnel WireGuard)',
                                '# PENTING: Jalankan script WireGuard terlebih dahulu!',
                                '# ============================================================',
                                '',
                                '# --- Hapus RADIUS entry lama jika ada ---',
                                '/radius remove [find address="' . $serverIp . '"]',
                                '',
                                '# --- Tambahkan RADIUS server ---',
                                '/radius add \\',
                                '    address=' . $serverIp . ' \\',
                                '    secret="' . $radiusSecret . '" \\',
                                '    service=hotspot,ppp \\',
                                '    authentication-port=1812 \\',
                                '    accounting-port=1813 \\',
                                '    timeout=3000ms \\',
                                '    comment="RAFEN RADIUS via WireGuard"',
                                '',
                                '# --- Aktifkan RADIUS untuk PPPoE ---',
                                '/ppp/aaa set use-radius=yes accounting=yes',
                                '',
                                '# --- Aktifkan RADIUS untuk Hotspot ---',
                                '/ip/hotspot/profile set [find] use-radius=yes',
                                '',
                                '# --- Verifikasi konfigurasi ---',
                                '/radius print',
                                '/ppp/aaa print',
                            ]);

                            // Script RADIUS Direct: tanpa WireGuard, pakai IP publik server
                            $radiusDirectScript = implode("\n", [
                                '# ============================================================',
                                '# RADIUS Direct IP untuk : ' . $peerName,
                                '# Mode       : Tanpa WireGuard (koneksi langsung via internet)',
                                '# Server IP  : ' . $wgHost . ' (IP publik server)',
                                '# ============================================================',
                                '',
                                '# --- Hapus RADIUS entry lama jika ada ---',
                                '/radius remove [find address="' . $wgHost . '"]',
                                '',
                                '# --- Tambahkan RADIUS server (IP publik server) ---',
                                '/radius add \\',
                                '    address=' . $wgHost . ' \\',
                                '    secret="' . $radiusSecret . '" \\',
                                '    service=hotspot,ppp \\',
                                '    authentication-port=1812 \\',
                                '    accounting-port=1813 \\',
                                '    timeout=3000ms \\',
                                '    comment="RAFEN RADIUS Direct"',
                                '',
                                '# --- Aktifkan RADIUS untuk PPPoE ---',
                                '/ppp/aaa set use-radius=yes accounting=yes',
                                '',
                                '# --- Aktifkan RADIUS untuk Hotspot ---',
                                '/ip/hotspot/profile set [find] use-radius=yes',
                                '',
                                '# --- Verifikasi konfigurasi ---',
                                '/radius print',
                                '/ppp/aaa print',
                                '',
                                '# --- Buka firewall outbound ke RADIUS server ---',
                                '# /ip/firewall/filter add chain=output dst-address=' . $wgHost . ' protocol=udp dst-port=1812-1813 action=accept comment="RAFEN RADIUS outbound"',
                            ]);

                            $scriptData = [
                                'wgScript'          => $wgScript,
                                'radiusScript'      => $radiusScript,
                                'radiusDirectScript'=> $radiusDirectScript,
                                'name'              => $peerName,
                                'host'              => $wgHost,
                                'listenPort'        => $listenPort,
                                'serverPubKey'      => $serverPubKey,
                                'serverIp'          => $serverIp,
                                'clientIp'          => $clientIp,
                                'clientPriv'        => $clientPriv,
                                'radiusSecret'      => $radiusSecret,
                            ];
                        @endphp
                        <tr id="wg-row-{{ $peer->id }}"
                            data-peer-id="{{ $peer->id }}"
                            data-destroy-url="{{ route('settings.wg.peers.destroy', $peer) }}"
                            data-sync-url="{{ route('settings.wg.peers.sync', $peer) }}"
                            data-update-url="{{ route('settings.wg.peers.update', $peer) }}"
                            data-create-nas-url="{{ route('settings.wg.peers.create-nas', $peer) }}"
                            data-keygen-url="{{ route('settings.wg.peers.keygen', $peer) }}"
                            data-has-nas="{{ $peer->mikrotik_connection_id ? '1' : '0' }}">
                            <td class="wg-col-router">
                                {{ $peer->mikrotikConnection?->name ?? '-' }}
                                @if($peer->is_active && $peer->vpn_ip && $peer->mikrotikConnection?->radius_secret)
                                    <br><span class="badge badge-info" style="font-size:10px;" title="RADIUS NAS menggunakan IP tunnel {{ $peer->vpn_ip }}">NAS: {{ $peer->vpn_ip }}</span>
                                @endif
                            </td>
                            <td class="wg-col-name">{{ $peer->name }}</td>
                            <td class="wg-col-ip">
                                @if($peer->vpn_ip)
                                    <a href="#" class="wg-ping-link" data-ip="{{ $peer->vpn_ip }}"
                                       title="Klik untuk ping {{ $peer->vpn_ip }}">{{ $peer->vpn_ip }}</a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="wg-col-pubkey">
                                <code style="font-size:10px;">{{ $peer->public_key ? \Illuminate\Support\Str::limit($peer->public_key, 20) : '-' }}</code>
                            </td>
                            <td class="wg-col-status">{{ $peer->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                            <td class="wg-col-sync">{{ $peer->last_synced_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                            <td class="text-right text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-success wg-sync-btn">Sync</button>
                                <button type="button" class="btn btn-sm btn-outline-primary"
                                        data-toggle="collapse" data-target="#wg-edit-{{ $peer->id }}">Edit</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary wg-script-btn"
                                        data-toggle="modal" data-target="#wg-script-modal"
                                        data-script='@json($scriptData)'>Script MikroTik</button>
                                @if(! $peer->mikrotik_connection_id && $peer->vpn_ip)
                                    <button type="button" class="btn btn-sm btn-outline-info wg-create-nas-btn"
                                            title="Buat entri router NAS baru dengan host = {{ $peer->vpn_ip }}">Buat NAS</button>
                                @endif
                                <button type="button" class="btn btn-sm btn-outline-danger wg-delete-btn">Hapus</button>
                            </td>
                        </tr>
                        <tr class="collapse" id="wg-edit-{{ $peer->id }}">
                            <td colspan="7" class="bg-light">
                                <form class="wg-update-form p-2"
                                      method="POST"
                                      action="{{ route('settings.wg.peers.update', $peer) }}"
                                      data-update-url="{{ route('settings.wg.peers.update', $peer) }}"
                                      data-peer-id="{{ $peer->id }}">
                                    @csrf
                                    @method('PATCH')
                                    <div class="form-row">
                                        <div class="form-group col-md-4">
                                            <label>Nama</label>
                                            <input type="text" name="name" value="{{ $peer->name }}" class="form-control" required>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>IP VPN</label>
                                            <input type="text" name="vpn_ip" value="{{ $peer->vpn_ip }}" class="form-control">
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Status</label>
                                            <select name="is_active" class="form-control">
                                                <option value="1" @selected($peer->is_active)>Aktif</option>
                                                <option value="0" @selected(! $peer->is_active)>Nonaktif</option>
                                            </select>
                                        </div>
                                    </div>
                                    {{-- Keypair section: readonly display + generate button --}}
                                    <div class="form-row mb-2">
                                        <div class="col-md-6">
                                            <label class="d-block mb-1">Public Key <small class="text-muted">(auto-update saat generate)</small></label>
                                            <code class="d-block text-truncate wg-edit-pubkey" style="font-size:11px;background:#f8f9fa;padding:6px 8px;border-radius:4px;border:1px solid #dee2e6;">{{ $peer->public_key ?? '-' }}</code>
                                            <input type="hidden" name="public_key" value="{{ $peer->public_key }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="d-block mb-1">Private Key <small class="text-muted">(auto-update saat generate)</small></label>
                                            <code class="d-block text-truncate wg-edit-privkey" style="font-size:11px;background:#f8f9fa;padding:6px 8px;border-radius:4px;border:1px solid #dee2e6;">{{ $peer->private_key ?? '-' }}</code>
                                            <input type="hidden" name="private_key" value="{{ $peer->private_key }}">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                                    <button type="button" class="btn btn-warning btn-sm wg-keygen-btn"
                                            data-keygen-url="{{ route('settings.wg.peers.keygen', $peer) }}"
                                            title="Generate keypair baru dan langsung tersimpan — copy ulang Script MikroTik setelah ini">
                                        <i class="fas fa-sync-alt mr-1"></i>Generate Ulang Keypair
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm"
                                            data-toggle="collapse" data-target="#wg-edit-{{ $peer->id }}">Batal</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr id="wg-empty-row"><td colspan="7" class="text-center p-4">Belum ada WireGuard peer.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Ping Modal --}}
    <div class="modal fade" id="wg-ping-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        <i class="fas fa-network-wired mr-1"></i>
                        Ping — <span id="wg-ping-ip"></span>
                    </h6>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body p-2">
                    <div id="wg-ping-loading" class="text-center py-3">
                        <i class="fas fa-spinner fa-spin mr-1"></i> Mengirim ping…
                    </div>
                    <pre id="wg-ping-output" class="mb-0 p-2" style="display:none;font-size:12px;background:#1e1e1e;color:#d4d4d4;border-radius:4px;white-space:pre-wrap;max-height:300px;overflow-y:auto;"></pre>
                    <div id="wg-ping-result" class="mt-2" style="display:none;"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" id="wg-ping-retry" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-redo mr-1"></i>Ulangi
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Script Modal --}}
    <div class="modal fade" id="wg-script-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Script MikroTik — WireGuard</h5>
                        <small class="text-muted" id="wg-modal-peer-name"></small>
                    </div>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    {{-- Host override --}}
                    <div class="form-group mb-3">
                        <label class="font-weight-bold mb-1">IP / Host Server WireGuard</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-server"></i></span>
                            </div>
                            <input type="text" id="wg-host-override" class="form-control"
                                   placeholder="Contoh: 203.0.113.1 atau vpn.domain.com"
                                   value="{{ $wg['host'] !== '' ? $wg['host'] : ($detectedIp ?? '') }}">
                            <div class="input-group-append">
                                <span class="input-group-text text-muted" style="font-size:12px;" id="wg-host-status"></span>
                            </div>
                        </div>
                        <small class="text-muted">Ubah di sini — script akan diperbarui otomatis.</small>
                    </div>

                    {{-- Tabs --}}
                    <ul class="nav nav-tabs mb-3" id="wg-script-tabs">
                        <li class="nav-item">
                            <a class="nav-link active" href="#" data-wg-tab="wg">
                                <i class="fas fa-shield-alt mr-1"></i>WireGuard Tunnel
                                <span class="badge badge-primary ml-1" style="font-size:9px;">ROS v7</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-wg-tab="radius">
                                <i class="fas fa-satellite-dish mr-1"></i>RADIUS via WireGuard
                                <span class="badge badge-info ml-1" style="font-size:9px;">Hotspot + PPPoE</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-wg-tab="direct">
                                <i class="fas fa-globe mr-1"></i>Direct IP
                                <span class="badge badge-warning ml-1" style="font-size:9px;">Tanpa WireGuard</span>
                            </a>
                        </li>
                    </ul>

                    {{-- WireGuard panel --}}
                    <div id="wg-panel-wg">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">
                                Tempel di <strong>Terminal MikroTik</strong> (WinBox / SSH). Membutuhkan RouterOS v7.1+.
                            </small>
                            <button type="button" class="btn btn-sm btn-success" id="wg-copy-wg">
                                <i class="fas fa-copy mr-1"></i>Copy Script
                            </button>
                        </div>
                        <textarea class="form-control" rows="22" id="wg-script-wg" readonly
                                  style="font-family:'Courier New',monospace;font-size:12.5px;background:#1e1e2e;color:#cdd6f4;border-radius:6px;resize:vertical;"></textarea>
                        <div class="alert alert-info mt-2 mb-0 py-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Pastikan port <strong id="wg-info-port"></strong>/UDP terbuka di firewall server.
                            Setelah script dijalankan, lanjutkan dengan script <strong>RADIUS Setup</strong> di tab berikutnya.
                        </div>
                    </div>

                    {{-- RADIUS panel --}}
                    <div id="wg-panel-radius" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">
                                Konfigurasi RADIUS untuk autentikasi Hotspot &amp; PPPoE via tunnel WireGuard.
                                <strong>Jalankan script WireGuard terlebih dahulu.</strong>
                            </small>
                            <button type="button" class="btn btn-sm btn-success" id="wg-copy-radius">
                                <i class="fas fa-copy mr-1"></i>Copy Script
                            </button>
                        </div>
                        <textarea class="form-control" rows="22" id="wg-script-radius" readonly
                                  style="font-family:'Courier New',monospace;font-size:12.5px;background:#1e1e2e;color:#cdd6f4;border-radius:6px;resize:vertical;"></textarea>
                        <div class="alert alert-warning mt-2 mb-0 py-2">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Pastikan tunnel WireGuard sudah aktif dan IP server
                            (<strong id="wg-info-server-ip"></strong>) terjangkau dari MikroTik sebelum menjalankan script ini.
                        </div>
                    </div>

                    {{-- Direct IP panel --}}
                    <div id="wg-panel-direct" style="display:none;">
                        <div class="alert alert-warning mb-3 py-2">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <strong>Mode Tanpa WireGuard:</strong> MikroTik terhubung langsung ke server via IP publik.
                            FreeRADIUS harus dapat diakses dari internet (port <strong>1812/UDP</strong> terbuka),
                            dan IP publik MikroTik harus didaftarkan sebagai client di FreeRADIUS.
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">
                                Konfigurasi RADIUS langsung ke IP publik server — <strong>tanpa tunnel WireGuard</strong>.
                            </small>
                            <button type="button" class="btn btn-sm btn-success" id="wg-copy-direct">
                                <i class="fas fa-copy mr-1"></i>Copy Script
                            </button>
                        </div>
                        <textarea class="form-control" rows="22" id="wg-script-direct" readonly
                                  style="font-family:'Courier New',monospace;font-size:12.5px;background:#1e1e2e;color:#cdd6f4;border-radius:6px;resize:vertical;"></textarea>
                        <div class="alert alert-info mt-2 mb-0 py-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Setelah script dijalankan, daftarkan IP publik MikroTik Anda sebagai
                            <strong>NAS client</strong> di halaman <a href="{{ route('settings.freeradius') }}" class="alert-link">Pengaturan FreeRADIUS</a>
                            agar FreeRADIUS menerima request dari router tersebut.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')
            ? document.querySelector('meta[name="csrf-token"]').content
            : '{{ csrf_token() }}';

        // Server WireGuard config — used by addPeerRow() to build script data client-side
        const wgServerConfig = {
            host:         @json($wg['host'] !== '' ? $wg['host'] : ($detectedIp ?? '')),
            serverPubKey: @json($wg['server_public_key'] !== '' ? $wg['server_public_key'] : ''),
            serverIp:     @json($wg['server_ip'] !== '' ? $wg['server_ip'] : '10.0.0.1'),
            listenPort:   @json($wg['listen_port'] !== '' ? $wg['listen_port'] : '51820'),
        };

        // ── Helpers ───────────────────────────────────────────────────────────
        function showAlert(message, type) {
            const el = document.getElementById('wg-alert');
            if (!el) return;
            el.className = 'alert alert-' + type + ' mb-3';
            el.textContent = message;
            el.style.display = '';
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            clearTimeout(el._timer);
            el._timer = setTimeout(function () { el.style.display = 'none'; }, 6000);
        }

        function ajaxJson(method, url, body) {
            return fetch(url, {
                method: method,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body ? JSON.stringify(body) : undefined,
            }).then(function (res) {
                return res.json().then(function (data) {
                    if (!res.ok) return Promise.reject(data);
                    return data;
                });
            });
        }

        function ajaxForm(method, url, formData) {
            const params = new URLSearchParams();
            formData.forEach(function (value, key) { params.append(key, value); });
            params.append('_method', method);
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: params,
            }).then(function (res) {
                return res.json().then(function (data) {
                    if (!res.ok) return Promise.reject(data);
                    return data;
                });
            });
        }

        function setRowBusy(row, busy) {
            row.querySelectorAll('button').forEach(function (btn) { btn.disabled = busy; });
        }

        // ── Update baris tabel ────────────────────────────────────────────────
        function updateRow(row, peer) {
            row.querySelector('.wg-col-router').textContent = peer.mikrotik_connection || '-';
            row.querySelector('.wg-col-name').textContent   = peer.name;
            const ipCell = row.querySelector('.wg-col-ip');
            if (peer.vpn_ip) {
                ipCell.innerHTML = '<a href="#" class="wg-ping-link" data-ip="' + peer.vpn_ip + '" title="Klik untuk ping ' + peer.vpn_ip + '">' + peer.vpn_ip + '</a>';
            } else {
                ipCell.textContent = '-';
            }
            const pkCell = row.querySelector('.wg-col-pubkey code');
            if (pkCell) pkCell.textContent = peer.public_key ? peer.public_key.substring(0, 20) + '…' : '-';
            row.querySelector('.wg-col-status').textContent = peer.is_active ? 'Aktif' : 'Nonaktif';
            row.querySelector('.wg-col-sync').textContent   = peer.last_synced_at || '-';
            row.dataset.destroyUrl = peer.destroy_url;
            row.dataset.syncUrl    = peer.sync_url;
            row.dataset.updateUrl  = peer.update_url;
            if (peer.keygen_url) row.dataset.keygenUrl = peer.keygen_url;

            // Perbarui data-script di tombol "Script MikroTik" agar selalu pakai keypair terbaru
            const scriptBtn = row.querySelector('.wg-script-btn');
            if (scriptBtn) {
                const newScript = buildScriptData(peer);
                scriptBtn.dataset.script = JSON.stringify(newScript).replace(/'/g, '&#39;');
            }

            const editRow = document.getElementById('wg-edit-' + peer.id);
            if (editRow) {
                const f = editRow.querySelector('form');
                if (f) {
                    if (peer.update_url) f.dataset.updateUrl = peer.update_url;
                    f.querySelector('[name="name"]').value    = peer.name;
                    f.querySelector('[name="vpn_ip"]').value  = peer.vpn_ip || '';
                    f.querySelector('[name="is_active"]').value = peer.is_active ? '1' : '0';
                    // Update hidden keypair inputs
                    const pubInput = f.querySelector('input[type="hidden"][name="public_key"]');
                    const privInput = f.querySelector('input[type="hidden"][name="private_key"]');
                    if (pubInput) pubInput.value = peer.public_key || '';
                    if (privInput) privInput.value = peer.private_key || '';
                    // Update readonly display
                    const pubCode = f.querySelector('.wg-edit-pubkey');
                    const privCode = f.querySelector('.wg-edit-privkey');
                    if (pubCode) pubCode.textContent = peer.public_key || '-';
                    if (privCode) privCode.textContent = peer.private_key || '-';
                }
            }
        }

        // ── Generate ulang keypair ────────────────────────────────────────────
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.wg-keygen-btn');
            if (!btn) return;

            const keygenUrl = btn.dataset.keygenUrl;
            if (!keygenUrl) return;

            if (!confirm('Generate keypair baru untuk peer ini?\n\nSetelah ini, jalankan Script MikroTik yang baru agar handshake kembali berfungsi.')) return;

            // Cari row dari form edit (data-peer-id di form) atau tr sebelumnya
            const form = btn.closest('form.wg-update-form');
            const peerId = form ? form.dataset.peerId : null;
            const row = peerId
                ? document.getElementById('wg-row-' + peerId)
                : btn.closest('tr[data-peer-id]');

            const origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Generating…';

            ajaxJson('POST', keygenUrl).then(function (data) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
                const peer = data.peer;
                const msg = (data.status || data.warning || 'Keypair baru berhasil di-generate.') + ' Jalankan Script MikroTik yang baru.';
                const alertType = data.warning ? 'warning' : 'success';
                if (peer && row) {
                    updateRow(row, peer);
                }
                showAlert(msg, alertType);
            }).catch(function (err) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
                showAlert((err && err.error) || 'Generate keypair gagal.', 'danger');
            });
        });

        // ── Hapus peer ────────────────────────────────────────────────────────
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.wg-delete-btn');
            if (!btn) return;
            if (!confirm('Hapus peer ini? Koneksi WireGuard dari router ini akan terputus.')) return;

            const row = btn.closest('tr[data-peer-id]');
            if (!row) return;
            setRowBusy(row, true);

            ajaxJson('DELETE', row.dataset.destroyUrl).then(function (data) {
                const editRow = document.getElementById('wg-edit-' + row.dataset.peerId);
                if (editRow) editRow.remove();
                row.remove();
                showAlert(data.status || 'Peer dihapus.', 'success');

                const tbody = document.getElementById('wg-peers-tbody');
                if (tbody && tbody.querySelectorAll('tr[data-peer-id]').length === 0) {
                    tbody.innerHTML = '<tr id="wg-empty-row"><td colspan="7" class="text-center p-4">Belum ada WireGuard peer.</td></tr>';
                }
            }).catch(function (err) {
                setRowBusy(row, false);
                showAlert((err && err.error) || 'Gagal menghapus peer.', 'danger');
            });
        });

        // ── Sync ──────────────────────────────────────────────────────────────
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.wg-sync-btn');
            if (!btn) return;

            const row = btn.closest('tr[data-peer-id]');
            if (!row) return;
            setRowBusy(row, true);
            btn.textContent = 'Syncing…';

            ajaxJson('POST', row.dataset.syncUrl).then(function (data) {
                row.querySelector('.wg-col-sync').textContent = data.last_synced_at || '-';
                btn.textContent = 'Sync';
                setRowBusy(row, false);
                showAlert(data.status || 'Sinkronisasi berhasil.', 'success');
            }).catch(function (err) {
                btn.textContent = 'Sync';
                setRowBusy(row, false);
                showAlert((err && err.error) || 'Sinkronisasi gagal.', 'danger');
            });
        });

        // ── Buat NAS ──────────────────────────────────────────────────────────
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.wg-create-nas-btn');
            if (!btn) return;

            const row = btn.closest('tr[data-peer-id]');
            if (!row) return;

            const vpnIp = row.querySelector('.wg-col-ip') ? row.querySelector('.wg-col-ip').textContent.trim() : '';
            if (!confirm('Buat router NAS baru dengan host = ' + vpnIp + '?\n\nAnda akan diarahkan ke halaman edit router untuk melengkapi konfigurasi.')) return;

            setRowBusy(row, true);
            btn.textContent = 'Membuat…';

            ajaxJson('POST', row.dataset.createNasUrl).then(function (data) {
                showAlert(data.status || 'NAS berhasil dibuat.', 'success');
                if (data.edit_url) {
                    if (typeof Turbo !== 'undefined') {
                        Turbo.visit(data.edit_url);
                    } else {
                        window.location.href = data.edit_url;
                    }
                } else {
                    btn.remove();
                    row.querySelector('.wg-col-router').textContent = data.peer.mikrotik_connection || '-';
                    setRowBusy(row, false);
                }
            }).catch(function (err) {
                btn.textContent = 'Buat NAS';
                setRowBusy(row, false);
                showAlert((err && err.error) || 'Gagal membuat NAS.', 'danger');
            });
        });

        // ── Update (edit form) ────────────────────────────────────────────────
        document.addEventListener('submit', function (e) {
            const form = e.target.closest('.wg-update-form');
            if (!form) return;
            e.preventDefault();
            e.stopPropagation();

            // Refresh CSRF token dari meta tag agar tidak stale
            const tokenInput = form.querySelector('[name="_token"]');
            if (tokenInput) tokenInput.value = csrfToken;

            // Ambil update URL dari form (data-update-url), fallback ke row sebelumnya
            const updateUrl = form.dataset.updateUrl || (function () {
                let r = form.closest('tr');
                if (r) r = r.previousElementSibling;
                while (r && !r.dataset.peerId) { r = r.previousElementSibling; }
                return r ? r.dataset.updateUrl : null;
            })();
            if (!updateUrl) return;

            const peerId   = form.dataset.peerId;
            const peerRow  = peerId ? document.getElementById('wg-row-' + peerId) : null;
            const submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            ajaxForm('PATCH', updateUrl, new FormData(form)).then(function (data) {
                if (submitBtn) submitBtn.disabled = false;
                if (peerRow && data.peer) updateRow(peerRow, data.peer);
                const editRow = form.closest('tr');
                if (editRow && typeof $ !== 'undefined') $(editRow).collapse('hide');
                showAlert(data.status || data.warning || 'Peer diperbarui.', data.warning ? 'warning' : 'success');
            }).catch(function (err) {
                if (submitBtn) submitBtn.disabled = false;
                let msg = (err && (err.error || err.message)) || 'Gagal menyimpan perubahan.';
                if (err && err.errors) msg = Object.values(err.errors).flat().join(' ');
                showAlert(msg, 'danger');
            });
        });

        // ── Store (tambah form) ───────────────────────────────────────────────
        const storeForm = document.getElementById('wg-store-form');
        if (storeForm) {
            storeForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const submitBtn = document.getElementById('wg-store-btn');
                if (submitBtn) submitBtn.disabled = true;

                ajaxForm('POST', storeForm.action, new FormData(storeForm)).then(function (data) {
                    if (submitBtn) submitBtn.disabled = false;
                    storeForm.reset();
                    showAlert(data.status || data.warning || 'Peer berhasil dibuat.', data.warning ? 'warning' : 'success');
                    addPeerRow(data.peer);
                }).catch(function (err) {
                    if (submitBtn) submitBtn.disabled = false;
                    let msg = (err && (err.error || err.message)) || 'Gagal menyimpan peer.';
                    if (err && err.errors) msg = Object.values(err.errors).flat().join(' ');
                    showAlert(msg, 'danger');
                });
            });
        }

        function buildScriptData(peer) {
            const host         = wgServerConfig.host || '<IP/Host>';
            const pubKey       = wgServerConfig.serverPubKey || '<SERVER_PUBLIC_KEY>';
            const serverIp     = wgServerConfig.serverIp || '10.0.0.1';
            const listenPort   = wgServerConfig.listenPort || '51820';
            const clientIp     = peer.vpn_ip || '<CLIENT_IP>';
            const clientPriv   = peer.private_key || '<CLIENT_PRIVATE_KEY>';
            const peerName     = peer.name || '';
            const radiusSecret = peer.radius_secret || '<RADIUS_SECRET>';

            const wgScript = [
                '# ============================================================',
                '# WireGuard Peer  : ' + peerName,
                '# RouterOS        : v7.1+',
                '# IP Tunnel       : ' + clientIp + '/24',
                '# Server Public Key: ' + pubKey,
                '# ============================================================',
                '',
                '# --- Bersihkan konfigurasi WireGuard lama (jika ada) ---',
                '/interface/wireguard/peers remove [find interface=wg-vpn]',
                '/interface/wireguard remove [find name=wg-vpn]',
                '/ip/address remove [find interface=wg-vpn]',
                '',
                '# --- Buat interface WireGuard ---',
                '/interface/wireguard add name=wg-vpn private-key="' + clientPriv + '" listen-port=13231 comment="RAFEN VPN"',
                '',
                '# --- Tambahkan peer (server) ---',
                '/interface/wireguard/peers add \\',
                '    interface=wg-vpn \\',
                '    public-key="' + pubKey + '" \\',
                '    endpoint-address=' + host + ' \\',
                '    endpoint-port=' + listenPort + ' \\',
                '    allowed-address=0.0.0.0/0 \\',
                '    persistent-keepalive=25',
                '',
                '# --- Assign IP address pada interface tunnel ---',
                '/ip/address add address=' + clientIp + '/24 interface=wg-vpn',
                '',
                '# --- Verifikasi koneksi ---',
                '/interface/wireguard/peers print',
                '/ip/address print where interface=wg-vpn',
            ].join('\n');

            const radiusScript = [
                '# ============================================================',
                '# RADIUS via WireGuard untuk : ' + peerName,
                '# Server RADIUS      : ' + serverIp + ' (IP tunnel WireGuard)',
                '# PENTING: Jalankan script WireGuard terlebih dahulu!',
                '# ============================================================',
                '',
                '# --- Hapus RADIUS entry lama jika ada ---',
                '/radius remove [find address="' + serverIp + '"]',
                '',
                '# --- Tambahkan RADIUS server ---',
                '/radius add \\',
                '    address=' + serverIp + ' \\',
                '    secret="' + radiusSecret + '" \\',
                '    service=hotspot,ppp \\',
                '    authentication-port=1812 \\',
                '    accounting-port=1813 \\',
                '    timeout=3000ms \\',
                '    comment="RAFEN RADIUS via WireGuard"',
                '',
                '# --- Aktifkan RADIUS untuk PPPoE ---',
                '/ppp/aaa set use-radius=yes accounting=yes',
                '',
                '# --- Aktifkan RADIUS untuk Hotspot ---',
                '/ip/hotspot/profile set [find] use-radius=yes',
                '',
                '# --- Verifikasi konfigurasi ---',
                '/radius print',
                '/ppp/aaa print',
            ].join('\n');

            const radiusDirectScript = [
                '# ============================================================',
                '# RADIUS Direct IP untuk : ' + peerName,
                '# Mode       : Tanpa WireGuard (koneksi langsung via internet)',
                '# Server IP  : ' + host + ' (IP publik server)',
                '# ============================================================',
                '',
                '# --- Hapus RADIUS entry lama jika ada ---',
                '/radius remove [find address="' + host + '"]',
                '',
                '# --- Tambahkan RADIUS server (IP publik server) ---',
                '/radius add \\',
                '    address=' + host + ' \\',
                '    secret="' + radiusSecret + '" \\',
                '    service=hotspot,ppp \\',
                '    authentication-port=1812 \\',
                '    accounting-port=1813 \\',
                '    timeout=3000ms \\',
                '    comment="RAFEN RADIUS Direct"',
                '',
                '# --- Aktifkan RADIUS untuk PPPoE ---',
                '/ppp/aaa set use-radius=yes accounting=yes',
                '',
                '# --- Aktifkan RADIUS untuk Hotspot ---',
                '/ip/hotspot/profile set [find] use-radius=yes',
                '',
                '# --- Verifikasi konfigurasi ---',
                '/radius print',
                '/ppp/aaa print',
                '',
                '# --- Buka firewall outbound ke RADIUS server ---',
                '# /ip/firewall/filter add chain=output dst-address=' + host + ' protocol=udp dst-port=1812-1813 action=accept comment="RAFEN RADIUS outbound"',
            ].join('\n');

            return {
                wgScript:           wgScript,
                radiusScript:       radiusScript,
                radiusDirectScript: radiusDirectScript,
                name:               peerName,
                host:               host,
                listenPort:         listenPort,
                serverPubKey:       pubKey,
                serverIp:           serverIp,
                clientIp:           clientIp,
                clientPriv:         clientPriv,
                radiusSecret:       radiusSecret,
            };
        }

        function addPeerRow(peer) {
            const tbody = document.getElementById('wg-peers-tbody');
            if (!tbody) return;
            const emptyRow = document.getElementById('wg-empty-row');
            if (emptyRow) emptyRow.remove();

            const pubKeyShort = peer.public_key ? peer.public_key.substring(0, 20) + '…' : '-';

            const scriptData = buildScriptData(peer);
            const scriptAttr = JSON.stringify(scriptData).replace(/'/g, '&#39;');

            const createNasBtn = (!peer.mikrotik_connection && peer.vpn_ip)
                ? '<button type="button" class="btn btn-sm btn-outline-info wg-create-nas-btn" title="Buat entri router NAS baru dengan host = ' + peer.vpn_ip + '">Buat NAS</button> '
                : '';

            tbody.insertAdjacentHTML('beforeend',
                '<tr id="wg-row-' + peer.id + '" data-peer-id="' + peer.id + '"' +
                ' data-destroy-url="' + peer.destroy_url + '"' +
                ' data-sync-url="' + peer.sync_url + '"' +
                ' data-update-url="' + peer.update_url + '"' +
                ' data-create-nas-url="' + peer.create_nas_url + '"' +
                ' data-keygen-url="' + (peer.keygen_url || '') + '"' +
                ' data-has-nas="' + (peer.mikrotik_connection ? '1' : '0') + '">' +
                '<td class="wg-col-router">' + (peer.mikrotik_connection || '-') + '</td>' +
                '<td class="wg-col-name">' + peer.name + '</td>' +
                '<td class="wg-col-ip">' + (peer.vpn_ip ? '<a href="#" class="wg-ping-link" data-ip="' + peer.vpn_ip + '" title="Klik untuk ping ' + peer.vpn_ip + '">' + peer.vpn_ip + '</a>' : '-') + '</td>' +
                '<td class="wg-col-pubkey"><code style="font-size:10px;">' + pubKeyShort + '</code></td>' +
                '<td class="wg-col-status">' + (peer.is_active ? 'Aktif' : 'Nonaktif') + '</td>' +
                '<td class="wg-col-sync">' + (peer.last_synced_at || '-') + '</td>' +
                '<td class="text-right text-nowrap">' +
                '<button type="button" class="btn btn-sm btn-outline-success wg-sync-btn">Sync</button> ' +
                '<button type="button" class="btn btn-sm btn-outline-primary" data-toggle="collapse" data-target="#wg-edit-' + peer.id + '">Edit</button> ' +
                '<button type="button" class="btn btn-sm btn-outline-secondary wg-script-btn" data-toggle="modal" data-target="#wg-script-modal" data-script=\'' + scriptAttr + '\'>Script MikroTik</button> ' +
                createNasBtn +
                '<button type="button" class="btn btn-sm btn-outline-danger wg-delete-btn">Hapus</button>' +
                '</td>' +
                '</tr>' +
                '<tr class="collapse" id="wg-edit-' + peer.id + '">' +
                '<td colspan="7" class="bg-light"><form class="wg-update-form p-2" data-update-url="' + peer.update_url + '" data-peer-id="' + peer.id + '">' +
                '<input type="hidden" name="_token" value="{{ csrf_token() }}">' +
                '<div class="form-row">' +
                '<div class="form-group col-md-4"><label>Nama</label><input type="text" name="name" value="' + peer.name + '" class="form-control" required></div>' +
                '<div class="form-group col-md-4"><label>IP VPN</label><input type="text" name="vpn_ip" value="' + (peer.vpn_ip || '') + '" class="form-control"></div>' +
                '<div class="form-group col-md-4"><label>Status</label><select name="is_active" class="form-control"><option value="1"' + (peer.is_active ? ' selected' : '') + '>Aktif</option><option value="0"' + (!peer.is_active ? ' selected' : '') + '>Nonaktif</option></select></div>' +
                '</div>' +
                '<div class="form-row mb-2">' +
                '<div class="col-md-6"><label class="d-block mb-1">Public Key <small class="text-muted">(auto-update saat generate)</small></label>' +
                '<code class="d-block text-truncate wg-edit-pubkey" style="font-size:11px;background:#f8f9fa;padding:6px 8px;border-radius:4px;border:1px solid #dee2e6;">' + (peer.public_key || '-') + '</code>' +
                '<input type="hidden" name="public_key" value="' + (peer.public_key || '') + '"></div>' +
                '<div class="col-md-6"><label class="d-block mb-1">Private Key <small class="text-muted">(auto-update saat generate)</small></label>' +
                '<code class="d-block text-truncate wg-edit-privkey" style="font-size:11px;background:#f8f9fa;padding:6px 8px;border-radius:4px;border:1px solid #dee2e6;">' + (peer.private_key || '-') + '</code>' +
                '<input type="hidden" name="private_key" value="' + (peer.private_key || '') + '"></div>' +
                '</div>' +
                '<button type="submit" class="btn btn-primary btn-sm">Simpan</button> ' +
                '<button type="button" class="btn btn-warning btn-sm wg-keygen-btn" data-keygen-url="' + (peer.keygen_url || '') + '" title="Generate keypair baru dan langsung tersimpan — copy ulang Script MikroTik setelah ini"><i class="fas fa-sync-alt mr-1"></i>Generate Ulang Keypair</button> ' +
                '<button type="button" class="btn btn-secondary btn-sm" data-toggle="collapse" data-target="#wg-edit-' + peer.id + '">Batal</button>' +
                '</form></td></tr>'
            );
        }

        // ── Script Modal ──────────────────────────────────────────────────────
        let currentScript = {};
        let activeTab     = 'wg';

        function getHost() {
            const el = document.getElementById('wg-host-override');
            return el ? el.value.trim() : '';
        }

        function rebuildScripts() {
            if (!currentScript.wgScript) return;
            const host = getHost() || '<IP/Host>';

            // Rebuild WG script: replace endpoint-address value live
            let wgText = currentScript.wgScript;
            wgText = wgText.replace(/endpoint-address=\S+/g, 'endpoint-address=' + host);
            const wgEl = document.getElementById('wg-script-wg');
            if (wgEl) wgEl.value = wgText;

            const radiusEl = document.getElementById('wg-script-radius');
            if (radiusEl) radiusEl.value = currentScript.radiusScript || '';

            // Rebuild Direct IP script: rebuild from scratch with new host
            const directScript = buildScriptData(Object.assign({}, currentScript, {
                vpn_ip: currentScript.clientIp,
                private_key: currentScript.clientPriv,
                radius_secret: currentScript.radiusSecret,
            }));
            const directEl = document.getElementById('wg-script-direct');
            if (directEl) directEl.value = directScript.radiusDirectScript || '';

            const portEl = document.getElementById('wg-info-port');
            if (portEl) portEl.textContent = currentScript.listenPort || '51820';

            const serverIpEl = document.getElementById('wg-info-server-ip');
            if (serverIpEl) serverIpEl.textContent = currentScript.serverIp || '10.0.0.1';

            const statusEl = document.getElementById('wg-host-status');
            if (statusEl) {
                const val = getHost();
                statusEl.textContent = val ? '✓ Digunakan' : 'Belum diisi';
                statusEl.style.color = val ? '#28a745' : '#dc3545';
            }
        }

        function switchTab(tab) {
            activeTab = tab;
            document.getElementById('wg-panel-wg').style.display     = tab === 'wg'     ? '' : 'none';
            document.getElementById('wg-panel-radius').style.display = tab === 'radius' ? '' : 'none';
            document.getElementById('wg-panel-direct').style.display = tab === 'direct' ? '' : 'none';
            document.querySelectorAll('#wg-script-tabs .nav-link').forEach(function (el) {
                el.classList.toggle('active', el.dataset.wgTab === tab);
            });
        }

        document.querySelectorAll('#wg-script-tabs .nav-link').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                switchTab(el.dataset.wgTab);
            });
        });

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.wg-script-btn');
            if (!btn) return;
            try {
                currentScript = JSON.parse(btn.dataset.script || '{}');
            } catch (_) {
                currentScript = {};
            }
            const nameEl = document.getElementById('wg-modal-peer-name');
            if (nameEl) nameEl.textContent = 'Peer: ' + (currentScript.name || '');
            switchTab('wg');
            rebuildScripts();
        });

        const hostInput = document.getElementById('wg-host-override');
        if (hostInput) hostInput.addEventListener('input', rebuildScripts);

        function copyTextarea(id, btn) {
            const el = document.getElementById(id);
            if (!el) return;
            el.select();
            document.execCommand('copy');
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check mr-1"></i>Tersalin!';
            setTimeout(function () { btn.innerHTML = orig; }, 2000);
        }

        const copyWgBtn = document.getElementById('wg-copy-wg');
        if (copyWgBtn) copyWgBtn.addEventListener('click', function () { copyTextarea('wg-script-wg', this); });

        const copyRadiusBtn = document.getElementById('wg-copy-radius');
        if (copyRadiusBtn) copyRadiusBtn.addEventListener('click', function () { copyTextarea('wg-script-radius', this); });

        const copyDirectBtn = document.getElementById('wg-copy-direct');
        if (copyDirectBtn) copyDirectBtn.addEventListener('click', function () { copyTextarea('wg-script-direct', this); });

        // ── Ping IP VPN ───────────────────────────────────────────────────────
        const pingUrl = '{{ route('settings.wg.ping') }}';
        let currentPingIp = null;

        function runPing(ip) {
            currentPingIp = ip;
            document.getElementById('wg-ping-ip').textContent       = ip;
            document.getElementById('wg-ping-loading').style.display = '';
            document.getElementById('wg-ping-output').style.display  = 'none';
            document.getElementById('wg-ping-result').style.display  = 'none';

            fetch(pingUrl + '?ip=' + encodeURIComponent(ip), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                document.getElementById('wg-ping-loading').style.display = 'none';
                const out = document.getElementById('wg-ping-output');
                out.textContent    = data.output || '(tidak ada output)';
                out.style.display  = '';
                const res = document.getElementById('wg-ping-result');
                res.innerHTML = data.alive
                    ? '<span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Host aktif</span>'
                    : '<span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i>Host tidak merespons</span>';
                res.style.display = '';
            })
            .catch(function () {
                document.getElementById('wg-ping-loading').style.display = 'none';
                document.getElementById('wg-ping-result').innerHTML      = '<span class="badge badge-danger">Ping gagal dijalankan</span>';
                document.getElementById('wg-ping-result').style.display  = '';
            });
        }

        document.addEventListener('click', function (e) {
            const link = e.target.closest('.wg-ping-link');
            if (!link) return;
            e.preventDefault();
            const ip = link.dataset.ip;
            $('#wg-ping-modal').modal('show');
            runPing(ip);
        });

        document.getElementById('wg-ping-retry')?.addEventListener('click', function () {
            if (currentPingIp) runPing(currentPingIp);
        });

        // Toggle manual keypair fields di form create
        document.getElementById('toggle-manual-keypair')?.addEventListener('click', function (e) {
            e.preventDefault();
            const fields = document.getElementById('manual-keypair-fields');
            const isHidden = fields.style.display === 'none';
            fields.style.display = isHidden ? '' : 'none';
            this.querySelector('small').textContent = isHidden ? 'Gunakan auto-generate' : 'Gunakan keypair sendiri';
            if (!isHidden) {
                fields.querySelectorAll('input[name="public_key"], input[name="private_key"]')
                      .forEach(function (i) { i.value = ''; });
            }
        });
    })();
    </script>
@endsection
