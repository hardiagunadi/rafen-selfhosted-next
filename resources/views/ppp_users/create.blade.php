@extends('layouts.admin')

@section('title', 'Tambah Pelanggan PPP')

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">

<div class="mf-page">

    {{-- Page header --}}
    <div class="mf-page-header">
        <div class="mf-page-header-left">
            <div class="mf-page-icon" style="background:linear-gradient(140deg,#0369a1,#0ea5e9);">
                <i class="fas fa-user-plus"></i>
            </div>
            <div>
                <div class="mf-page-title">Tambah Pelanggan <span class="mf-dim">[PPP]</span></div>
                <div class="mf-page-sub">Isi data paket &amp; info pelanggan</div>
            </div>
        </div>
        <div class="mf-header-actions">
            <a href="{{ route('ppp-users.index') }}" class="mf-btn-back">
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

    <form action="{{ route('ppp-users.store') }}" method="POST" id="ppp-user-form" novalidate>
    @csrf

    {{-- Inline alert --}}
    <div id="form-alert" class="mf-alert mf-alert-danger" style="display:none;"></div>

    <div class="mf-grid">

        {{-- Tabs --}}
        <div class="mf-section">
            <div class="mf-tabs-nav">
                <button type="button" class="mf-tab-btn mf-tab-active" data-target="tab-paket">
                    <i class="fas fa-box mr-1"></i> Paket Langganan
                </button>
                <button type="button" class="mf-tab-btn" data-target="tab-info">
                    <i class="fas fa-id-card mr-1"></i> Info Pelanggan
                </button>
            </div>

            {{-- ─── Tab: Paket Langganan ─────────────────────────── --}}
            <div class="mf-tab-pane" id="tab-paket">

                {{-- Owner + Paket + Status Reg --}}
                <div class="mf-section-header">
                    <div class="mf-section-icon" style="background:linear-gradient(140deg,#0369a1,#0ea5e9);">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="mf-section-title">Paket &amp; Status</div>
                </div>
                <div class="mf-section-body">
                    <div class="mf-row mf-row-3">
                        <div class="mf-field">
                            <label class="mf-label">Owner Data <span class="mf-req">*</span></label>
                            <select name="owner_id" class="mf-input @error('owner_id') mf-input-error @enderror" required>
                                @foreach($owners as $owner)
                                    <option value="{{ $owner->id }}" @selected(old('owner_id') == $owner->id)>{{ $owner->name }}</option>
                                @endforeach
                            </select>
                            @error('owner_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Paket Langganan (Profil PPP) <span class="mf-req">*</span></label>
                            <select name="ppp_profile_id" class="mf-input @error('ppp_profile_id') mf-input-error @enderror" required>
                                <option value="" disabled @selected(! old('ppp_profile_id'))>- pilih paket -</option>
                                @foreach($profiles as $profile)
                                    <option value="{{ $profile->id }}" @selected(old('ppp_profile_id') == $profile->id)>
                                        {{ $profile->name }} - Rp {{ number_format((float) $profile->harga_modal, 0, ',', '.') }} - {{ (int) $profile->masa_aktif }} {{ $profile->satuan }}
                                    </option>
                                @endforeach
                            </select>
                            @error('ppp_profile_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Status Registrasi <span class="mf-req">*</span></label>
                            <div class="mf-radio-group">
                                <label class="mf-radio-label">
                                    <input type="radio" name="status_registrasi" value="aktif" @checked(old('status_registrasi', 'aktif') === 'aktif') required>
                                    <span>AKTIF SEKARANG</span>
                                </label>
                                <label class="mf-radio-label">
                                    <input type="radio" name="status_registrasi" value="on_process" @checked(old('status_registrasi') === 'on_process')>
                                    <span>ON PROCESS</span>
                                </label>
                            </div>
                            @error('status_registrasi')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Tipe Pembayaran <span class="mf-req">*</span></label>
                            <select name="tipe_pembayaran" class="mf-input @error('tipe_pembayaran') mf-input-error @enderror" required>
                                <option value="prepaid" @selected(old('tipe_pembayaran', 'prepaid') === 'prepaid')>PREPAID</option>
                                <option value="postpaid" @selected(old('tipe_pembayaran') === 'postpaid')>POSTPAID</option>
                            </select>
                            @error('tipe_pembayaran')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Status Bayar <span class="mf-req">*</span></label>
                            <select name="status_bayar" class="mf-input @error('status_bayar') mf-input-error @enderror" required>
                                <option value="sudah_bayar" @selected(old('status_bayar') === 'sudah_bayar')>SUDAH BAYAR</option>
                                <option value="belum_bayar" @selected(old('status_bayar', 'belum_bayar') === 'belum_bayar')>BELUM BAYAR</option>
                            </select>
                            @error('status_bayar')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Status Akun <span class="mf-req">*</span></label>
                            <select name="status_akun" class="mf-input @error('status_akun') mf-input-error @enderror" required>
                                <option value="enable" @selected(old('status_akun', 'enable') === 'enable')>ENABLE</option>
                                <option value="disable" @selected(old('status_akun') === 'disable')>DISABLE</option>
                            </select>
                            @error('status_akun')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Tipe Service <span class="mf-req">*</span></label>
                            <select name="tipe_service" class="mf-input @error('tipe_service') mf-input-error @enderror" required>
                                <option value="pppoe" @selected(old('tipe_service', 'pppoe') === 'pppoe')>PPPoE</option>
                                <option value="l2tp_pptp" @selected(old('tipe_service') === 'l2tp_pptp')>L2TP/PPTP</option>
                                <option value="openvpn_sstp" @selected(old('tipe_service') === 'openvpn_sstp')>OPENVPN/SSTP</option>
                            </select>
                            @error('tipe_service')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mf-row">
                        <div class="mf-field" style="justify-content:flex-end;gap:.5rem;">
                            <label class="mf-label">Opsi Lanjutan</label>
                            <label class="mf-switch">
                                <input type="checkbox" id="prorata_otomatis" name="prorata_otomatis" value="1" @checked(old('prorata_otomatis'))>
                                <span class="mf-switch-track"></span>
                                <span class="mf-switch-label">Prorata Otomatis</span>
                            </label>
                            <label class="mf-switch">
                                <input type="checkbox" id="promo_aktif" name="promo_aktif" value="1" @checked(old('promo_aktif'))>
                                <span class="mf-switch-track"></span>
                                <span class="mf-switch-label">Aktifkan Promo</span>
                            </label>
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Durasi Promo <span class="mf-opt">Opsional</span></label>
                            <input type="number" name="durasi_promo_bulan" value="{{ old('durasi_promo_bulan') }}"
                                class="mf-input @error('durasi_promo_bulan') mf-input-error @enderror" min="0" placeholder="bulan">
                            @error('durasi_promo_bulan')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Biaya Aktivasi <span class="mf-opt">Opsional</span></label>
                            <input type="text" id="biaya_instalasi_display"
                                value="{{ old('biaya_instalasi') ? number_format((float) old('biaya_instalasi'), 0, ',', '.') : '' }}"
                                class="mf-input @error('biaya_instalasi') mf-input-error @enderror"
                                autocomplete="off" inputmode="numeric"
                                oninput="formatBiayaAktivasi(this)" onblur="formatBiayaAktivasi(this)"
                                placeholder="Rp 0">
                            <input type="hidden" name="biaya_instalasi" id="biaya_instalasi_value" value="{{ old('biaya_instalasi') }}">
                            @error('biaya_instalasi')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Ubah Jatuh Tempo <span class="mf-opt">Opsional</span></label>
                            <input type="date" name="jatuh_tempo" value="{{ old('jatuh_tempo') }}"
                                class="mf-input @error('jatuh_tempo') mf-input-error @enderror">
                            @error('jatuh_tempo')<div class="mf-feedback">{{ $message }}</div>@enderror
                            <div class="mf-hint">Jika tidak diisi, prorata diabaikan. Tidak berlaku untuk paket unlimited atau masa aktif &lt; 3 hari.</div>
                        </div>
                    </div>

                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Aksi Jatuh Tempo</label>
                            <select name="aksi_jatuh_tempo" class="mf-input @error('aksi_jatuh_tempo') mf-input-error @enderror">
                                <option value="isolir" @selected(old('aksi_jatuh_tempo', 'isolir') === 'isolir')>ISOLIR INTERNET</option>
                                <option value="tetap_terhubung" @selected(old('aksi_jatuh_tempo') === 'tetap_terhubung')>TETAP TERHUBUNG</option>
                            </select>
                            @error('aksi_jatuh_tempo')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Tipe IP Address <span class="mf-req">*</span></label>
                            <select name="tipe_ip" class="mf-input @error('tipe_ip') mf-input-error @enderror" id="tipe-ip-select">
                                <option value="dhcp" @selected(old('tipe_ip', 'dhcp') === 'dhcp')>DHCP</option>
                                <option value="static" @selected(old('tipe_ip') === 'static')>Static</option>
                            </select>
                            @error('tipe_ip')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div id="static-ip-section" style="display:none;">
                        <div class="mf-row">
                            <div class="mf-field">
                                <label class="mf-label">Group Profil</label>
                                <select name="profile_group_id" class="mf-input @error('profile_group_id') mf-input-error @enderror">
                                    <option value="">- pilih -</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->id }}" @selected(old('profile_group_id') == $group->id)>{{ $group->name }}</option>
                                    @endforeach
                                </select>
                                @error('profile_group_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mf-field">
                                <label class="mf-label">IP Address</label>
                                <input type="text" name="ip_static" value="{{ old('ip_static') }}"
                                    class="mf-input @error('ip_static') mf-input-error @enderror"
                                    placeholder="xxx.xxx.xxx.xxx">
                                @error('ip_static')<div class="mf-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ─── Tab: Info Pelanggan ─────────────────────────── --}}
            <div class="mf-tab-pane" id="tab-info" style="display:none;">

                {{-- Identifikasi --}}
                <div class="mf-section-header">
                    <div class="mf-section-icon" style="background:linear-gradient(140deg,#334155,#64748b);">
                        <i class="fas fa-fingerprint"></i>
                    </div>
                    <div class="mf-section-title">Identifikasi Pelanggan</div>
                </div>
                <div class="mf-section-body">
                    <div class="mf-row mf-row-3">
                        <div class="mf-field">
                            <label class="mf-label">ODP Master <span class="mf-opt">Opsional</span></label>
                            <select name="odp_id" class="mf-input @error('odp_id') mf-input-error @enderror" data-native-select="true">
                                <option value="">- pilih ODP -</option>
                                @foreach($odps as $odp)
                                    <option value="{{ $odp->id }}" @selected(old('odp_id') == $odp->id)>
                                        {{ $odp->code }} - {{ $odp->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('odp_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">ODP | POP <span class="mf-opt">Opsional</span></label>
                            <input type="text" name="odp_pop" value="{{ old('odp_pop') }}"
                                class="mf-input @error('odp_pop') mf-input-error @enderror">
                            @error('odp_pop')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">
                                ID Pelanggan
                                <span class="mf-opt" style="background:rgba(14,165,233,.12);color:#0369a1;border:1px solid rgba(14,165,233,.3);">Auto</span>
                            </label>
                            <div class="mf-input-group">
                                <input type="text" name="customer_id" id="customer_id" value="{{ old('customer_id') }}"
                                    class="mf-input @error('customer_id') mf-input-error @enderror"
                                    placeholder="Memuat..." readonly>
                                <button type="button" class="mf-input-btn" id="btn-generate-customer-id" title="Generate ulang ID">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                                <button type="button" class="mf-input-btn" id="btn-unlock-customer-id" title="Edit manual">
                                    <i class="fas fa-lock"></i>
                                </button>
                            </div>
                            @error('customer_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                            <div class="mf-hint" id="customer-id-hint">ID otomatis di-generate. Klik <i class="fas fa-lock"></i> untuk edit manual.</div>
                        </div>
                    </div>

                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Nama <span class="mf-req">*</span></label>
                            <input type="text" name="customer_name" value="{{ old('customer_name') }}"
                                class="mf-input @error('customer_name') mf-input-error @enderror" required>
                            @error('customer_name')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">No. NIK <span class="mf-req">*</span></label>
                            <input type="text" name="nik" value="{{ old('nik') }}"
                                class="mf-input @error('nik') mf-input-error @enderror" required>
                            @error('nik')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Nomor HP <span class="mf-req">*</span></label>
                            <input type="text" name="nomor_hp" value="{{ old('nomor_hp') }}"
                                class="mf-input @error('nomor_hp') mf-input-error @enderror"
                                placeholder="08xxxx (otomatis jadi 628xx)" required>
                            @error('nomor_hp')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Email <span class="mf-req">*</span></label>
                            <input type="email" name="email" value="{{ old('email') }}"
                                class="mf-input @error('email') mf-input-error @enderror" required>
                            @error('email')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mf-field">
                        <label class="mf-label">Alamat <span class="mf-req">*</span></label>
                        <textarea name="alamat" class="mf-input @error('alamat') mf-input-error @enderror" rows="2" required>{{ old('alamat') }}</textarea>
                        @error('alamat')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                {{-- Lokasi --}}
                <div class="mf-section-header" style="margin-top:.25rem;">
                    <div class="mf-section-icon" style="background:linear-gradient(140deg,#065f46,#10b981);">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="mf-section-title">Lokasi <span class="mf-opt">Opsional</span></div>
                </div>
                <div class="mf-section-body">
                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Latitude</label>
                            <input type="text" name="latitude" id="latitude" value="{{ old('latitude') }}"
                                class="mf-input @error('latitude') mf-input-error @enderror">
                            @error('latitude')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Longitude</label>
                            <input type="text" name="longitude" id="longitude" value="{{ old('longitude') }}"
                                class="mf-input @error('longitude') mf-input-error @enderror">
                            @error('longitude')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mf-test-bar">
                        <button type="button" class="mf-btn-test" id="btn-capture-gps">
                            <i class="fas fa-location-arrow mr-1"></i>Ambil Titik GPS (3 Sampel)
                        </button>
                        <button type="button" class="mf-btn-test" id="btn-toggle-map-preview"
                            style="background:linear-gradient(140deg,#334155,#64748b);">
                            <i class="fas fa-map-marked-alt mr-1"></i>Lihat Maps
                        </button>
                        <span class="mf-hint" id="location-meta-info">
                            Akurasi: {{ old('location_accuracy_m') ? old('location_accuracy_m').' m' : '-' }}
                        </span>
                    </div>

                    <div id="ppp-location-map-wrapper" class="d-none">
                        <div id="ppp-location-map" style="height:320px;border:1px solid var(--app-border,#d7e1ee);border-radius:9px;" class="mb-2"></div>
                        <div class="mf-hint" id="location-map-info">
                            Gunakan layer Earth untuk cek visual satelit. Marker bisa digeser untuk koreksi titik presisi.
                        </div>
                    </div>

                    <input type="hidden" name="location_accuracy_m" id="location_accuracy_m" value="{{ old('location_accuracy_m') }}">
                    <input type="hidden" name="location_capture_method" id="location_capture_method" value="{{ old('location_capture_method') }}">
                    <input type="hidden" name="location_captured_at" id="location_captured_at" value="{{ old('location_captured_at') }}">
                </div>

                {{-- Kredensial --}}
                <div class="mf-section-header" style="margin-top:.25rem;">
                    <div class="mf-section-icon" style="background:linear-gradient(140deg,#b45309,#f59e0b);">
                        <i class="fas fa-key"></i>
                    </div>
                    <div class="mf-section-title">Kredensial &amp; Catatan</div>
                </div>
                <div class="mf-section-body">
                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Metode Login <span class="mf-req">*</span></label>
                            <select name="metode_login" class="mf-input @error('metode_login') mf-input-error @enderror" id="metode-login-select" required>
                                <option value="username_password" @selected(old('metode_login', 'username_password') === 'username_password')>USERNAME &amp; PASSWORD</option>
                                <option value="username_equals_password" @selected(old('metode_login') === 'username_equals_password')>USERNAME = PASSWORD</option>
                            </select>
                            <div class="mf-hint">Jika pilih USERNAME = PASSWORD, password akan disamakan dengan username.</div>
                            @error('metode_login')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Username <span class="mf-req">*</span></label>
                            <input type="text" name="username" value="{{ old('username') }}"
                                class="mf-input @error('username') mf-input-error @enderror" required>
                            @error('username')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div id="ppp-password-row">
                        <div class="mf-field" style="max-width:50%;padding-right:.425rem;">
                            <label class="mf-label">Password PPPoE/L2TP/OVPN</label>
                            <input type="text" name="ppp_password" value="{{ old('ppp_password') }}"
                                class="mf-input @error('ppp_password') mf-input-error @enderror">
                            @error('ppp_password')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Password Clientarea</label>
                            <input type="text" name="password_clientarea" value="{{ old('password_clientarea') }}"
                                class="mf-input @error('password_clientarea') mf-input-error @enderror" id="password-clientarea">
                            @error('password_clientarea')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Catatan <span class="mf-opt">Opsional</span></label>
                            <textarea name="catatan" class="mf-input @error('catatan') mf-input-error @enderror" rows="2">{{ old('catatan') }}</textarea>
                            @error('catatan')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>{{-- /.mf-grid --}}

    {{-- Footer --}}
    <div class="mf-footer">
        <a href="{{ route('ppp-users.index') }}" class="mf-btn-cancel">Batal</a>
        <button type="submit" class="mf-btn-submit" id="submit-btn">
            <i class="fas fa-save mr-1"></i> Simpan Pelanggan
        </button>
    </div>

    </form>
</div>

<style>
/* ── mf-* design system ─────────────────────────────────────── */
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

/* ── Alerts ── */
.mf-alert { display:flex;align-items:flex-start;gap:.5rem;padding:.85rem 1rem;border-radius:12px;font-size:.84rem; }
.mf-alert-danger { background:#fef2f2;border:1px solid #fecaca;color:#991b1b; }

/* ── Tabs ── */
.mf-tabs-nav { display:flex;gap:0;border-bottom:2px solid var(--app-border,#d7e1ee);padding:.75rem 1.25rem 0;background:#f8fbff; }
.mf-tab-btn { background:none;border:none;cursor:pointer;font-size:.84rem;font-weight:600;color:var(--app-text-soft,#5b6b83);padding:.5rem .9rem .75rem;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color 140ms,border-color 140ms; }
.mf-tab-btn:hover { color:var(--app-text,#0f172a); }
.mf-tab-btn.mf-tab-active { color:#0369a1;border-bottom-color:#0369a1; }
.mf-tab-pane { /* visible by default if display:block */ }

/* ── Grid & section ── */
.mf-grid { display:flex;flex-direction:column;gap:1rem; }
.mf-section { background:var(--app-surface,#fff);border:1px solid var(--app-border,#d7e1ee);border-radius:16px;box-shadow:0 4px 16px rgba(15,23,42,.05);overflow:hidden; }
.mf-section-header { display:flex;align-items:center;gap:.75rem;padding:.8rem 1.25rem;background:#f8fbff;border-bottom:1px solid var(--app-border,#d7e1ee); }
.mf-section-icon { width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.9rem;color:#fff;flex-shrink:0; }
.mf-section-title { font-size:.9rem;font-weight:700;color:var(--app-text,#0f172a); }
.mf-section-body { padding:1.1rem 1.25rem;display:flex;flex-direction:column;gap:.85rem; }

/* ── Rows ── */
.mf-row { display:grid;grid-template-columns:repeat(2,1fr);gap:.85rem; }
.mf-row-3 { grid-template-columns:repeat(3,1fr); }
@media (max-width:767px) { .mf-row,.mf-row-3 { grid-template-columns:1fr; } }
.mf-field { display:flex;flex-direction:column;gap:.3rem; }

/* ── Labels & inputs ── */
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

/* ── Input group ── */
.mf-input-group { display:flex;align-items:stretch;gap:0; }
.mf-input-group .mf-input { border-radius:9px 0 0 9px;flex:1; }
.mf-input-btn { height:38px;padding:0 .65rem;background:#f1f5f9;border:1px solid var(--app-border,#d7e1ee);border-left:none;color:var(--app-text-soft,#5b6b83);font-size:.8rem;cursor:pointer;transition:background 140ms; }
.mf-input-btn:last-child { border-radius:0 9px 9px 0; }
.mf-input-btn:hover { background:#e2e8f0; }

/* ── Switch ── */
.mf-switch { display:inline-flex;align-items:center;gap:.6rem;cursor:pointer;user-select:none; }
.mf-switch input { display:none; }
.mf-switch-track { width:42px;height:24px;border-radius:99px;flex-shrink:0;background:#d1d5db;transition:background 200ms;position:relative; }
.mf-switch-track::after { content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:transform 200ms; }
.mf-switch input:checked ~ .mf-switch-track { background:linear-gradient(140deg,#0369a1,#0ea5e9); }
.mf-switch input:checked ~ .mf-switch-track::after { transform:translateX(18px); }
.mf-switch-label { font-size:.84rem;font-weight:600;color:var(--app-text,#0f172a); }

/* ── Radio group ── */
.mf-radio-group { display:flex;flex-wrap:wrap;gap:.4rem; }
.mf-radio-label { display:inline-flex;align-items:center;gap:.4rem;font-size:.82rem;font-weight:600;color:var(--app-text,#0f172a);cursor:pointer;padding:.3rem .65rem;border-radius:8px;border:1px solid var(--app-border,#d7e1ee);background:#fff;transition:border-color 140ms,background 140ms; }
.mf-radio-label input { accent-color:#0369a1; }
.mf-radio-label:has(input:checked) { border-color:#0369a1;background:rgba(14,165,233,.07);color:#0369a1; }

/* ── Test bar & btn ── */
.mf-test-bar { display:flex;align-items:center;flex-wrap:wrap;gap:.75rem; }
.mf-btn-test { display:inline-flex;align-items:center;height:36px;padding:0 1rem;border-radius:9px;border:none;background:linear-gradient(140deg,#0369a1,#0ea5e9);color:#fff;font-size:.82rem;font-weight:600;cursor:pointer;transition:opacity 140ms,transform 140ms; }
.mf-btn-test:hover { opacity:.88;transform:translateY(-1px); }

/* ── Footer ── */
.mf-footer { display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;padding:1rem 1.25rem;background:var(--app-surface,#fff);border:1px solid var(--app-border,#d7e1ee);border-radius:14px; }
.mf-btn-cancel { font-size:.84rem;font-weight:600;color:var(--app-text-soft,#5b6b83);text-decoration:none;padding:.4rem .75rem;transition:color 140ms; }
.mf-btn-cancel:hover { color:var(--app-text,#0f172a);text-decoration:none; }
.mf-btn-submit { display:inline-flex;align-items:center;height:38px;padding:0 1.4rem;border-radius:10px;border:none;background:linear-gradient(140deg,#0369a1,#0ea5e9);color:#fff;font-size:.88rem;font-weight:700;cursor:pointer;box-shadow:0 3px 12px rgba(14,165,233,.25);transition:opacity 140ms,transform 140ms; }
.mf-btn-submit:hover { opacity:.9;transform:translateY(-1px); }
</style>

<script>
    const ipSelect = document.getElementById('tipe-ip-select');
    const staticSection = document.getElementById('static-ip-section');
    function toggleStatic() {
        staticSection.style.display = ipSelect.value === 'static' ? 'block' : 'none';
    }
    ipSelect.addEventListener('change', toggleStatic);
    toggleStatic();

    // ── Tabs ────────────────────────────────────────────────────
    document.querySelectorAll('.mf-tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.mf-tab-btn').forEach(function(b) { b.classList.remove('mf-tab-active'); });
            document.querySelectorAll('.mf-tab-pane').forEach(function(p) { p.style.display = 'none'; });
            btn.classList.add('mf-tab-active');
            document.getElementById(btn.dataset.target).style.display = 'block';
        });
    });

    const metodeLoginSelect = document.getElementById('metode-login-select');
    const pppPasswordInput = document.querySelector('input[name="ppp_password"]');
    const pppPasswordRow = document.getElementById('ppp-password-row');
    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');
    const locationAccuracyInput = document.getElementById('location_accuracy_m');
    const locationMethodInput = document.getElementById('location_capture_method');
    const locationCapturedAtInput = document.getElementById('location_captured_at');
    const locationMetaInfo = document.getElementById('location-meta-info');
    const captureGpsButton = document.getElementById('btn-capture-gps');
    const toggleMapButton = document.getElementById('btn-toggle-map-preview');
    const locationMapWrapper = document.getElementById('ppp-location-map-wrapper');
    const locationMapInfo = document.getElementById('location-map-info');
    const earthFocusZoom = 17;
    let isMapVisible = false;
    let locationMap = null;
    let locationMarker = null;

    function median(values) {
        const sorted = values.slice().sort((a, b) => a - b);
        const middle = Math.floor(sorted.length / 2);
        return sorted.length % 2 === 0
            ? (sorted[middle - 1] + sorted[middle]) / 2
            : sorted[middle];
    }

    function parseCoordinate(value) {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : null;
    }

    function setMapInfo(message, className) {
        if (! locationMapInfo) { return; }
        locationMapInfo.textContent = message;
        locationMapInfo.className = 'mf-hint ' + (className || '');
    }

    function initLocationMap() {
        if (locationMap || typeof L === 'undefined') { return; }
        const mapContainer = document.getElementById('ppp-location-map');
        if (! mapContainer) { return; }
        const initialLat = parseCoordinate(latitudeInput?.value);
        const initialLng = parseCoordinate(longitudeInput?.value);
        const initialPoint = (initialLat !== null && initialLng !== null) ? [initialLat, initialLng] : [-7.36, 109.90];
        const initialZoom = (initialLat !== null && initialLng !== null) ? earthFocusZoom : 12;
        locationMap = L.map('ppp-location-map').setView(initialPoint, initialZoom);
        const earthLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19, maxNativeZoom: earthFocusZoom, attribution: 'Tiles &copy; Esri'
        }).addTo(locationMap);
        const streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
        });
        L.control.layers({ Earth: earthLayer, Street: streetLayer }).addTo(locationMap);
        locationMarker = L.marker(initialPoint, { draggable: true }).addTo(locationMap);
        locationMarker.on('dragend', function () {
            const position = locationMarker.getLatLng();
            setMapPoint(position.lat, position.lng, false);
        });
        locationMap.on('click', function (event) {
            setMapPoint(event.latlng.lat, event.latlng.lng, false);
        });
    }

    function setMapVisibility(visible) {
        isMapVisible = visible;
        if (locationMapWrapper) { locationMapWrapper.classList.toggle('d-none', ! visible); }
        if (toggleMapButton) {
            toggleMapButton.innerHTML = visible
                ? '<i class="fas fa-eye-slash mr-1"></i>Sembunyikan Maps'
                : '<i class="fas fa-map-marked-alt mr-1"></i>Lihat Maps';
        }
        if (! visible) { return; }
        ensureLocationMapReady();
        syncMapFromInputs();
    }

    function ensureLocationMapReady() {
        initLocationMap();
        if (locationMap) { setTimeout(function () { locationMap.invalidateSize(); }, 0); }
    }

    function getFocusZoom() {
        if (! locationMap) { return earthFocusZoom; }
        const maxZoom = locationMap.getMaxZoom();
        return (typeof maxZoom === 'number' && Number.isFinite(maxZoom)) ? Math.min(maxZoom, earthFocusZoom) : earthFocusZoom;
    }

    function setMapPoint(lat, lng, shouldFocusMap) {
        if (latitudeInput) { latitudeInput.value = lat.toFixed(7); }
        if (longitudeInput) { longitudeInput.value = lng.toFixed(7); }
        if (locationMarker) { locationMarker.setLatLng([lat, lng]); }
        if (shouldFocusMap && locationMap) { locationMap.setView([lat, lng], getFocusZoom()); }
        setMapInfo('Titik disetel: ' + lat.toFixed(7) + ', ' + lng.toFixed(7), 'text-success');
    }

    function setLocationMeta(accuracy, method) {
        if (locationAccuracyInput) { locationAccuracyInput.value = accuracy.toFixed(2); }
        if (locationMethodInput) { locationMethodInput.value = method; }
        if (locationCapturedAtInput) { locationCapturedAtInput.value = new Date().toISOString(); }
        if (locationMetaInfo) { locationMetaInfo.textContent = 'Akurasi: ' + accuracy.toFixed(1) + ' m (' + method + ')'; }
    }

    function captureGpsSamples() {
        if (!navigator.geolocation) { alert('Browser tidak mendukung geolocation.'); return; }
        captureGpsButton.disabled = true;
        captureGpsButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Mengambil titik...';
        const samples = [];
        function takeSample() {
            navigator.geolocation.getCurrentPosition(function (position) {
                samples.push({ latitude: position.coords.latitude, longitude: position.coords.longitude, accuracy: position.coords.accuracy });
                if (samples.length < 3) { setTimeout(takeSample, 1200); return; }
                const latMedian = median(samples.map(s => s.latitude));
                const lngMedian = median(samples.map(s => s.longitude));
                setMapVisibility(true);
                ensureLocationMapReady();
                setMapPoint(latMedian, lngMedian, true);
                setLocationMeta(median(samples.map(s => s.accuracy)), 'gps');
                captureGpsButton.disabled = false;
                captureGpsButton.innerHTML = '<i class="fas fa-location-arrow mr-1"></i>Ambil Titik GPS (3 Sampel)';
            }, function (error) {
                let message = 'Gagal mengambil lokasi.';
                if (error.code === 1) message = 'Izin lokasi ditolak.';
                if (error.code === 2) message = 'Lokasi tidak tersedia.';
                if (error.code === 3) message = 'Permintaan lokasi timeout.';
                alert(message);
                captureGpsButton.disabled = false;
                captureGpsButton.innerHTML = '<i class="fas fa-location-arrow mr-1"></i>Ambil Titik GPS (3 Sampel)';
            }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
        }
        takeSample();
    }

    if (captureGpsButton) { captureGpsButton.addEventListener('click', captureGpsSamples); }
    if (toggleMapButton) { toggleMapButton.addEventListener('click', function () { setMapVisibility(! isMapVisible); }); }

    function syncMapFromInputs() {
        if (! isMapVisible) { return; }
        const lat = parseCoordinate(latitudeInput?.value);
        const lng = parseCoordinate(longitudeInput?.value);
        if (lat === null || lng === null) { return; }
        ensureLocationMapReady();
        setMapPoint(lat, lng, true);
    }

    if (latitudeInput) { latitudeInput.addEventListener('change', syncMapFromInputs); }
    if (longitudeInput) { longitudeInput.addEventListener('change', syncMapFromInputs); }

    setMapVisibility(false);

    function togglePasswordRequirement() {
        const isUsernamePassword = metodeLoginSelect.value === 'username_password';
        if (pppPasswordInput && pppPasswordRow) {
            pppPasswordInput.required = isUsernamePassword;
            pppPasswordRow.style.display = isUsernamePassword ? '' : 'none';
        }
    }
    metodeLoginSelect.addEventListener('change', togglePasswordRequirement);
    togglePasswordRequirement();

    document.getElementById('ppp-user-form').addEventListener('submit', function (e) {
        const alertBox = document.getElementById('form-alert');
        alertBox.style.display = 'none';
        alertBox.innerHTML = '';

        // Determine which tab pane's fields are invalid
        const tabPaket = document.getElementById('tab-paket');
        const tabInfo  = document.getElementById('tab-info');
        const paketSelect = document.querySelector('select[name="ppp_profile_id"]');

        const paketInvalid = Array.from(tabPaket.querySelectorAll('[required]')).some(el => ! el.checkValidity());
        const infoInvalid  = Array.from(tabInfo.querySelectorAll('[required]')).some(el => ! el.checkValidity());
        const messages = [];

        if (! paketSelect.value) { messages.push('Paket Langganan belum diisi.'); }
        if (paketInvalid || infoInvalid || ! this.checkValidity()) {
            if (paketInvalid) {
                messages.push('Bagian Paket Langganan belum lengkap. Lengkapi field wajib.');
            } else if (infoInvalid) {
                messages.push('Bagian Info Pelanggan belum lengkap. Lengkapi field wajib.');
            } else {
                messages.push('Pastikan semua field wajib terisi dengan benar.');
            }
        }

        if (messages.length) {
            e.preventDefault();
            e.stopPropagation();
            alertBox.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>' + messages.join(' ');
            alertBox.style.display = 'flex';
            // switch to offending tab
            const targetTab = (paketInvalid || ! paketSelect.value) ? 'tab-paket' : 'tab-info';
            document.querySelectorAll('.mf-tab-btn').forEach(function(b) { b.classList.remove('mf-tab-active'); });
            document.querySelectorAll('.mf-tab-pane').forEach(function(p) { p.style.display = 'none'; });
            document.querySelector('[data-target="' + targetTab + '"]').classList.add('mf-tab-active');
            document.getElementById(targetTab).style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
</script>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
function formatBiayaAktivasi(el) {
    var raw = el.value.replace(/\./g, '').replace(/[^0-9]/g, '');
    var num = parseInt(raw, 10) || 0;
    el.value = num > 0 ? num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
    document.getElementById('biaya_instalasi_value').value = num || 0;
}
$(function() {
    var $field = $('#customer_id');
    var $btnGen = $('#btn-generate-customer-id');
    var $btnUnlock = $('#btn-unlock-customer-id');
    var $hint = $('#customer-id-hint');
    var isLocked = true;

    function fetchCustomerId() {
        $btnGen.prop('disabled', true).find('i').addClass('fa-spin');
        $.get('{{ route('ppp-users.generate-customer-id') }}', function(res) {
            $field.val(res.customer_id);
        }).always(function() {
            $btnGen.prop('disabled', false).find('i').removeClass('fa-spin');
        });
    }

    function setLocked(locked) {
        isLocked = locked;
        $field.prop('readonly', locked);
        if (locked) {
            $btnUnlock.html('<i class="fas fa-lock"></i>').attr('title', 'Edit manual');
            $btnGen.show();
            $hint.html('ID otomatis di-generate. Klik <i class="fas fa-lock"></i> untuk edit manual.');
        } else {
            $btnUnlock.html('<i class="fas fa-lock-open"></i>').attr('title', 'Kunci & generate otomatis');
            $btnGen.hide();
            $hint.text('Mode edit manual aktif.');
            $field.focus();
        }
    }

    if (!$field.val()) { fetchCustomerId(); }
    $btnGen.on('click', fetchCustomerId);
    $btnUnlock.on('click', function() {
        if (isLocked) { setLocked(false); } else { setLocked(true); if (!$field.val()) fetchCustomerId(); }
    });
});
</script>
@endpush
