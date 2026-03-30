@extends('layouts.admin')

@section('title', 'Edit User PPP')

@section('content')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
    <style>
        #ppp-location-map {
            height: 320px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }

        /* ── mf-* design system ── */
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
        .mf-btn-outline { display:inline-flex;align-items:center;padding:.4rem .95rem;border-radius:9px;border:1px solid var(--app-border,#d7e1ee);background:#fff;color:var(--app-text,#0f172a);font-size:.82rem;font-weight:600;text-decoration:none;transition:background 140ms,transform 140ms; }
        .mf-btn-outline:hover { background:#f1f5ff;transform:translateY(-1px);color:var(--app-text,#0f172a);text-decoration:none; }
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

        /* ── mf-tabs ── */
        .mf-tabs-nav { display:flex;gap:0;border-bottom:2px solid var(--app-border,#d7e1ee);padding:.75rem 1.25rem 0;background:#f8fbff; }
        .mf-tab-btn { background:none;border:none;cursor:pointer;font-size:.84rem;font-weight:600;color:var(--app-text-soft,#5b6b83);padding:.5rem .9rem .75rem;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color 140ms,border-color 140ms; }
        .mf-tab-btn.mf-tab-active { color:#0369a1;border-bottom-color:#0369a1; }

        /* radio group */
        .mf-radio-group { display:flex;gap:1.25rem;flex-wrap:wrap; }
        .mf-radio-label { display:inline-flex;align-items:center;gap:.4rem;font-size:.84rem;font-weight:600;color:var(--app-text,#0f172a);cursor:pointer; }
    </style>

<div class="mf-page">

    {{-- Page header --}}
    <div class="mf-page-header">
        <div class="mf-page-header-left">
            <div class="mf-page-icon" style="background:linear-gradient(140deg,#b45309,#f59e0b);">
                <i class="fas fa-pen"></i>
            </div>
            <div>
                <div class="mf-page-title">Edit Pelanggan</div>
                <div class="mf-page-sub"><span style="color:#b45309;font-weight:600;">{{ $pppUser->customer_name }}</span></div>
            </div>
        </div>
        <div class="mf-header-actions">
            <a href="{{ route('ppp-users.nota-aktivasi', $pppUser) }}" target="_blank" class="mf-btn-outline">
                <i class="fas fa-print mr-1"></i> Nota Aktivasi
            </a>
            <a href="{{ route('ppp-users.index') }}" class="mf-btn-back">
                <i class="fas fa-arrow-left mr-1"></i> Kembali
            </a>
        </div>
    </div>

    {{-- Main tabs --}}
    <div class="mf-section">
        <div class="mf-tabs-nav" id="mainTabNav">
            <button class="mf-tab-btn mf-tab-active" data-target="main-edit-pane"><i class="fas fa-edit mr-1"></i>Edit Pelanggan</button>
            <button class="mf-tab-btn" data-target="main-invoice-pane"><i class="fas fa-file-invoice mr-1"></i>Invoice &amp; Session</button>
            <button class="mf-tab-btn" data-target="main-dialup-pane"><i class="fas fa-history mr-1"></i>Riwayat Dialup</button>
            <button class="mf-tab-btn" data-target="main-cpe-pane"><i class="fas fa-router mr-1"></i>Perangkat CPE</button>
        </div>

        {{-- TAB: EDIT FORM --}}
        <div class="mf-tab-pane" id="main-edit-pane">
            <form action="{{ route('ppp-users.update', $pppUser) }}" method="POST" id="form-edit-ppp">
                @csrf
                @method('PUT')

                {{-- Validation errors --}}
                @if ($errors->any())
                <div style="padding:1rem 1.25rem;">
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
                </div>
                @endif

                {{-- Inner tabs: Paket / Info --}}
                <div class="mf-tabs-nav" id="innerTabNav">
                    <button type="button" class="mf-tab-btn mf-tab-active" data-target="inner-paket-pane">Paket Langganan</button>
                    <button type="button" class="mf-tab-btn" data-target="inner-info-pane">Info Pelanggan</button>
                </div>

                {{-- TAB: Paket Langganan --}}
                <div class="mf-tab-pane" id="inner-paket-pane" style="padding:1.1rem 1.25rem;display:flex;flex-direction:column;gap:.85rem;">

                    <div class="mf-row mf-row-3">
                        <div class="mf-field">
                            <label class="mf-label">Owner Data</label>
                            <select name="owner_id" class="mf-input @error('owner_id') mf-input-error @enderror">
                                @foreach($owners as $owner)
                                    <option value="{{ $owner->id }}" @selected(old('owner_id', $pppUser->owner_id) == $owner->id)>{{ $owner->name }}</option>
                                @endforeach
                            </select>
                            @error('owner_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Paket Langganan (Profil PPP)</label>
                            <select name="ppp_profile_id" class="mf-input @error('ppp_profile_id') mf-input-error @enderror">
                                <option value="">- pilih paket -</option>
                                @foreach($profiles as $profile)
                                    <option value="{{ $profile->id }}" @selected(old('ppp_profile_id', $pppUser->ppp_profile_id) == $profile->id)>
                                        {{ $profile->name }} - Rp {{ number_format((float) $profile->harga_modal, 0, ',', '.') }} - {{ (int) $profile->masa_aktif }} {{ $profile->satuan }}
                                    </option>
                                @endforeach
                            </select>
                            @error('ppp_profile_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Status Registrasi</label>
                            <div class="mf-radio-group" style="margin-top:.35rem;">
                                <label class="mf-radio-label"><input type="radio" name="status_registrasi" value="aktif" @checked(old('status_registrasi', $pppUser->status_registrasi) === 'aktif')> AKTIF SEKARANG</label>
                                <label class="mf-radio-label"><input type="radio" name="status_registrasi" value="on_process" @checked(old('status_registrasi', $pppUser->status_registrasi) === 'on_process')> ON PROCESS</label>
                            </div>
                            @error('status_registrasi')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Tipe Pembayaran</label>
                            <select name="tipe_pembayaran" class="mf-input @error('tipe_pembayaran') mf-input-error @enderror">
                                <option value="prepaid" @selected(old('tipe_pembayaran', $pppUser->tipe_pembayaran) === 'prepaid')>PREPAID</option>
                                <option value="postpaid" @selected(old('tipe_pembayaran', $pppUser->tipe_pembayaran) === 'postpaid')>POSTPAID</option>
                            </select>
                            @error('tipe_pembayaran')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Status Bayar</label>
                            <select name="status_bayar" class="mf-input @error('status_bayar') mf-input-error @enderror">
                                <option value="sudah_bayar" @selected(old('status_bayar', $pppUser->status_bayar) === 'sudah_bayar')>SUDAH BAYAR</option>
                                <option value="belum_bayar" @selected(old('status_bayar', $pppUser->status_bayar) === 'belum_bayar')>BELUM BAYAR</option>
                            </select>
                            @error('status_bayar')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Status Akun</label>
                            <select name="status_akun" class="mf-input @error('status_akun') mf-input-error @enderror">
                                <option value="enable" @selected(old('status_akun', $pppUser->status_akun) === 'enable')>ENABLE</option>
                                <option value="disable" @selected(old('status_akun', $pppUser->status_akun) === 'disable')>DISABLE</option>
                                <option value="isolir" @selected(old('status_akun', $pppUser->status_akun) === 'isolir')>ISOLIR</option>
                            </select>
                            @error('status_akun')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Tipe Service</label>
                            <select name="tipe_service" class="mf-input @error('tipe_service') mf-input-error @enderror">
                                <option value="pppoe" @selected(old('tipe_service', $pppUser->tipe_service) === 'pppoe')>PPPoE</option>
                                <option value="l2tp_pptp" @selected(old('tipe_service', $pppUser->tipe_service) === 'l2tp_pptp')>L2TP/PPTP</option>
                                <option value="openvpn_sstp" @selected(old('tipe_service', $pppUser->tipe_service) === 'openvpn_sstp')>OPENVPN/SSTP</option>
                            </select>
                            @error('tipe_service')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Opsi Billing</label>
                            <div style="display:flex;flex-direction:column;gap:.5rem;margin-top:.25rem;">
                                <label class="mf-switch">
                                    <input type="checkbox" id="prorata_otomatis" name="prorata_otomatis" value="1" @checked(old('prorata_otomatis', $pppUser->prorata_otomatis))>
                                    <span class="mf-switch-track"></span>
                                    <span class="mf-switch-label">Prorata Otomatis</span>
                                </label>
                                <label class="mf-switch">
                                    <input type="checkbox" id="promo_aktif" name="promo_aktif" value="1" @checked(old('promo_aktif', $pppUser->promo_aktif))>
                                    <span class="mf-switch-track"></span>
                                    <span class="mf-switch-label">Promo (Aktifkan Promo)</span>
                                </label>
                            </div>
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Durasi Promo (bulan)</label>
                            <input type="number" name="durasi_promo_bulan" value="{{ old('durasi_promo_bulan', $pppUser->durasi_promo_bulan) }}" class="mf-input @error('durasi_promo_bulan') mf-input-error @enderror" min="0">
                            @error('durasi_promo_bulan')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Biaya Aktivasi</label>
                            <input type="text" id="biaya_instalasi_display" value="{{ number_format((float) old('biaya_instalasi', $pppUser->biaya_instalasi), 0, ',', '.') }}" class="mf-input @error('biaya_instalasi') mf-input-error @enderror" autocomplete="off" inputmode="numeric" oninput="formatBiayaAktivasi(this)" onblur="formatBiayaAktivasi(this)">
                            <input type="hidden" name="biaya_instalasi" id="biaya_instalasi_value" value="{{ old('biaya_instalasi', $pppUser->biaya_instalasi) }}">
                            @error('biaya_instalasi')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Ubah Jatuh Tempo <span class="mf-opt">opsional</span></label>
                            <input type="date" name="jatuh_tempo" value="{{ old('jatuh_tempo', optional($pppUser->jatuh_tempo)->format('Y-m-d')) }}" class="mf-input @error('jatuh_tempo') mf-input-error @enderror">
                            @error('jatuh_tempo')<div class="mf-feedback">{{ $message }}</div>@enderror
                            <div class="mf-hint">Jika tidak diisi, prorata diabaikan. Tidak berlaku untuk paket unlimited atau masa aktif &lt; 3 hari.</div>
                        </div>
                    </div>

                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Aksi Jatuh Tempo</label>
                            <select name="aksi_jatuh_tempo" class="mf-input @error('aksi_jatuh_tempo') mf-input-error @enderror">
                                <option value="isolir" @selected(old('aksi_jatuh_tempo', $pppUser->aksi_jatuh_tempo) === 'isolir')>ISOLIR INTERNET</option>
                                <option value="tetap_terhubung" @selected(old('aksi_jatuh_tempo', $pppUser->aksi_jatuh_tempo) === 'tetap_terhubung')>TETAP TERHUBUNG</option>
                            </select>
                            @error('aksi_jatuh_tempo')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Tipe IP Address</label>
                            <select name="tipe_ip" class="mf-input @error('tipe_ip') mf-input-error @enderror" id="tipe-ip-select">
                                <option value="dhcp" @selected(old('tipe_ip', $pppUser->tipe_ip) === 'dhcp')>DHCP</option>
                                <option value="static" @selected(old('tipe_ip', $pppUser->tipe_ip) === 'static')>Static</option>
                            </select>
                            @error('tipe_ip')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div id="static-ip-section" style="display: none;">
                        <div class="mf-row">
                            <div class="mf-field">
                                <label class="mf-label">Group Profil</label>
                                <select name="profile_group_id" class="mf-input @error('profile_group_id') mf-input-error @enderror">
                                    <option value="">- pilih -</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->id }}" @selected(old('profile_group_id', $pppUser->profile_group_id) == $group->id)>{{ $group->name }}</option>
                                    @endforeach
                                </select>
                                @error('profile_group_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mf-field">
                                <label class="mf-label">IP Address</label>
                                <input type="text" name="ip_static" value="{{ old('ip_static', $pppUser->ip_static) }}" class="mf-input @error('ip_static') mf-input-error @enderror" placeholder="xxx.xxx.xxx.xxx">
                                @error('ip_static')<div class="mf-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                </div>{{-- end inner-paket-pane --}}

                {{-- TAB: Info Pelanggan --}}
                <div class="mf-tab-pane" id="inner-info-pane" style="display:none;padding:1.1rem 1.25rem;display:none;flex-direction:column;gap:.85rem;">

                    <div class="mf-row mf-row-3">
                        <div class="mf-field">
                            <label class="mf-label">ODP Master <span class="mf-opt">opsional</span></label>
                            <select name="odp_id" class="mf-input @error('odp_id') mf-input-error @enderror" data-native-select="true">
                                <option value="">- pilih ODP -</option>
                                @foreach($odps as $odp)
                                    <option value="{{ $odp->id }}" @selected(old('odp_id', $pppUser->odp_id) == $odp->id)>
                                        {{ $odp->code }} - {{ $odp->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('odp_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">ODP | POP <span class="mf-opt">opsional</span></label>
                            <input type="text" name="odp_pop" value="{{ old('odp_pop', $pppUser->odp_pop) }}" class="mf-input @error('odp_pop') mf-input-error @enderror">
                            @error('odp_pop')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">ID Pelanggan</label>
                            <input type="text" name="customer_id" value="{{ old('customer_id', $pppUser->customer_id) }}" class="mf-input @error('customer_id') mf-input-error @enderror">
                            @error('customer_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Nama</label>
                            <input type="text" name="customer_name" value="{{ old('customer_name', $pppUser->customer_name) }}" class="mf-input @error('customer_name') mf-input-error @enderror">
                            @error('customer_name')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">No. NIK</label>
                            <input type="text" name="nik" value="{{ old('nik', $pppUser->nik) }}" class="mf-input @error('nik') mf-input-error @enderror">
                            @error('nik')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Nomor HP</label>
                            <input type="text" name="nomor_hp" value="{{ old('nomor_hp', $pppUser->nomor_hp) }}" class="mf-input @error('nomor_hp') mf-input-error @enderror" placeholder="08xxxx (otomatis jadi 628xx)">
                            @error('nomor_hp')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Email</label>
                            <input type="email" name="email" value="{{ old('email', $pppUser->email) }}" class="mf-input @error('email') mf-input-error @enderror">
                            @error('email')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mf-field">
                        <label class="mf-label">Alamat</label>
                        <textarea name="alamat" class="mf-input @error('alamat') mf-input-error @enderror" rows="2">{{ old('alamat', $pppUser->alamat) }}</textarea>
                        @error('alamat')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Latitude <span class="mf-opt">opsional</span></label>
                            <input type="text" name="latitude" id="latitude" value="{{ old('latitude', $pppUser->latitude) }}" class="mf-input @error('latitude') mf-input-error @enderror">
                            @error('latitude')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Longitude <span class="mf-opt">opsional</span></label>
                            <input type="text" name="longitude" id="longitude" value="{{ old('longitude', $pppUser->longitude) }}" class="mf-input @error('longitude') mf-input-error @enderror">
                            @error('longitude')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mf-row">
                        <div class="mf-field">
                            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-capture-gps">
                                    <i class="fas fa-location-arrow mr-1"></i>Ambil Titik GPS (3 Sampel)
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-toggle-map-preview">
                                    <i class="fas fa-map-marked-alt mr-1"></i>Lihat Maps
                                </button>
                            </div>
                        </div>
                        <div class="mf-field" style="justify-content:flex-end;">
                            <div class="mf-hint" id="location-meta-info">
                                Akurasi: {{ old('location_accuracy_m', $pppUser->location_accuracy_m) ? number_format((float) old('location_accuracy_m', $pppUser->location_accuracy_m), 1) . ' m' : '-' }}
                            </div>
                        </div>
                    </div>

                    <div id="ppp-location-map-wrapper" class="d-none">
                        <div id="ppp-location-map" class="mb-3"></div>
                        <small class="text-muted d-block mb-3" id="location-map-info">
                            Gunakan layer Earth untuk cek visual satelit. Marker bisa digeser untuk koreksi titik presisi.
                        </small>
                    </div>

                    <input type="hidden" name="location_accuracy_m" id="location_accuracy_m" value="{{ old('location_accuracy_m', $pppUser->location_accuracy_m) }}">
                    <input type="hidden" name="location_capture_method" id="location_capture_method" value="{{ old('location_capture_method', $pppUser->location_capture_method) }}">
                    <input type="hidden" name="location_captured_at" id="location_captured_at" value="{{ old('location_captured_at', optional($pppUser->location_captured_at)->toIso8601String()) }}">

                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Metode Login</label>
                            <select name="metode_login" class="mf-input @error('metode_login') mf-input-error @enderror" id="metode-login-select">
                                <option value="username_password" @selected(old('metode_login', $pppUser->metode_login) === 'username_password')>USERNAME & PASSWORD</option>
                                <option value="username_equals_password" @selected(old('metode_login', $pppUser->metode_login) === 'username_equals_password')>USERNAME = PASSWORD</option>
                            </select>
                            @error('metode_login')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Username</label>
                            <input type="text" name="username" value="{{ old('username', $pppUser->username) }}" class="mf-input @error('username') mf-input-error @enderror">
                            @error('username')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mf-row" id="ppp-password-row">
                        <div class="mf-field">
                            <label class="mf-label">Password PPPoE/L2TP/OVPN</label>
                            <input type="text" name="ppp_password" value="{{ old('ppp_password', $pppUser->ppp_password) }}" class="mf-input @error('ppp_password') mf-input-error @enderror">
                            @error('ppp_password')<div class="mf-feedback">{{ $message }}</div>@enderror
                            <div class="mf-hint">Jika metode login "USERNAME = PASSWORD" dan dikosongkan, password PPP otomatis sama dengan username.</div>
                        </div>
                    </div>

                    <div class="mf-row">
                        <div class="mf-field">
                            <label class="mf-label">Password Clientarea</label>
                            <input type="text" name="password_clientarea" value="{{ old('password_clientarea', $pppUser->password_clientarea) }}" class="mf-input @error('password_clientarea') mf-input-error @enderror" id="password-clientarea">
                            @error('password_clientarea')<div class="mf-feedback">{{ $message }}</div>@enderror
                            <div class="mf-hint">Jika metode login "USERNAME = PASSWORD" dan kosong, password akan disamakan dengan username.</div>
                        </div>
                        <div class="mf-field">
                            <label class="mf-label">Catatan <span class="mf-opt">opsional</span></label>
                            <textarea name="catatan" class="mf-input @error('catatan') mf-input-error @enderror" rows="2">{{ old('catatan', $pppUser->catatan) }}</textarea>
                            @error('catatan')<div class="mf-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                </div>{{-- end inner-info-pane --}}

                @if(!auth()->user()->isTeknisi())
                <div style="margin:0 1.25rem 1.25rem;">
                    <div class="mf-section">
                        <div class="mf-section-header">
                            <div class="mf-section-icon" style="background:linear-gradient(140deg,#b45309,#f59e0b);"><i class="fas fa-user-hard-hat"></i></div>
                            <div class="mf-section-title">Penugasan Teknisi</div>
                        </div>
                        <div class="mf-section-body">
                            <div class="mf-row">
                                <div class="mf-field">
                                    <label class="mf-label">Teknisi yang Ditugaskan <span class="mf-opt">opsional</span></label>
                                    <select name="assigned_teknisi_id" class="mf-input @error('assigned_teknisi_id') mf-input-error @enderror">
                                        <option value="">-- Tidak ada / Semua teknisi bisa akses --</option>
                                        @foreach($teknisiList as $teknisi)
                                            <option value="{{ $teknisi->id }}" {{ old('assigned_teknisi_id', $pppUser->assigned_teknisi_id) == $teknisi->id ? 'selected' : '' }}>
                                                {{ $teknisi->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('assigned_teknisi_id')<div class="mf-feedback">{{ $message }}</div>@enderror
                                    <div class="mf-hint">Jika diisi, hanya teknisi yang dipilih yang dapat mengelola pelanggan ini. Teknisi lain hanya bisa melihat data.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <div class="mf-footer" style="margin:0 1.25rem 1.25rem;border-radius:14px;">
                    <a href="{{ route('ppp-users.index') }}" class="mf-btn-cancel">Batal</a>
                    <button type="submit" class="mf-btn-submit"><i class="fas fa-save mr-1"></i> Update</button>
                </div>

            </form>
        </div>{{-- end main-edit-pane --}}

        {{-- TAB: INVOICE & SESSION --}}
        <div class="mf-tab-pane" id="main-invoice-pane" style="display:none;padding:1.1rem 1.25rem;">
            <p class="text-muted small"><strong>*** Penghitung trafik direset disetiap waktu jatuh tempo</strong></p>

            {{-- Session info cards --}}
            @php
                $activeSession = \App\Models\RadiusAccount::where('username', $pppUser->username)
                    ->where('is_active', true)
                    ->whereNotNull('mikrotik_connection_id')
                    ->latest('updated_at')
                    ->first();
                $formatBytes = function (int $bytes): string {
                    if ($bytes >= 1099511627776) return round($bytes / 1099511627776, 2) . ' TB';
                    if ($bytes >= 1073741824)    return round($bytes / 1073741824, 2) . ' GB';
                    if ($bytes >= 1048576)       return round($bytes / 1048576, 2) . ' MB';
                    if ($bytes >= 1024)          return round($bytes / 1024, 2) . ' KB';
                    return $bytes . ' B';
                };
                $bytesIn  = (int) ($activeSession?->bytes_in  ?? 0);
                $bytesOut = (int) ($activeSession?->bytes_out ?? 0);
                $totalBytes  = $bytesIn + $bytesOut;
                $uploadDisplay   = $formatBytes($bytesIn);
                $downloadDisplay = $formatBytes($bytesOut);
                $totalDisplay    = $formatBytes($totalBytes);

                // Parse uptime string (e.g. "2d17h23m7s", "4h33m25s", "2d17h23m") → total seconds
                $uptimeSeconds = 0;
                if ($activeSession?->uptime) {
                    preg_match_all('/(\d+)([wdhms])/', $activeSession->uptime, $matches, PREG_SET_ORDER);
                    foreach ($matches as $m) {
                        $uptimeSeconds += match($m[2]) {
                            'w' => (int)$m[1] * 604800,
                            'd' => (int)$m[1] * 86400,
                            'h' => (int)$m[1] * 3600,
                            'm' => (int)$m[1] * 60,
                            's' => (int)$m[1],
                            default => 0,
                        };
                    }
                }
                $baseSeconds = $uptimeSeconds;
            @endphp
            <div class="row mb-3">
                <div class="col-md-3 mb-2 d-flex">
                    <div class="p-3 rounded text-white w-100 {{ $activeSession ? 'bg-info' : 'bg-secondary' }}">
                        <div class="small mb-1" style="opacity:.8">Perangkat</div>
                        <div><i class="fas fa-network-wired mr-1"></i><strong>{{ $activeSession?->caller_id ?? '-' }}</strong></div>
                        <div class="small mt-1" style="opacity:.9"><strong>{{ $activeSession ? 'connected' : 'disconnected' }}</strong></div>
                    </div>
                </div>
                <div class="col-md-3 mb-2 d-flex">
                    <div class="p-3 rounded w-100 bg-warning text-dark">
                        <div class="small mb-1" style="opacity:.7">Waktu Online</div>
                        <div><i class="fas fa-clock mr-1"></i><strong id="uptime-counter">{{ $activeSession?->uptime ?? '-' }}</strong></div>
                        <div class="small mt-1" style="opacity:.75">
                            @if($activeSession?->updated_at)
                                sync: <span id="sync-timer" data-ts="{{ $activeSession->updated_at->timestamp }}"></span>
                            @else
                                &nbsp;
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-2 d-flex">
                    <div class="p-3 rounded text-white w-100 bg-success">
                        <div class="small mb-1" style="opacity:.8">Quota Terpakai</div>
                        <div><i class="fas fa-chart-area mr-1"></i><strong>{{ $totalDisplay }}</strong></div>
                        <div class="small mt-1" style="opacity:.9">
                            <i class="fas fa-upload mr-1"></i>{{ $uploadDisplay }}
                            &nbsp;&nbsp;
                            <i class="fas fa-download mr-1"></i>{{ $downloadDisplay }}
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-2 d-flex">
                    <div class="p-3 rounded text-white w-100 bg-primary">
                        <div class="small mb-1" style="opacity:.8">IP Address</div>
                        <div><i class="fas fa-signal mr-1"></i><strong>{{ $activeSession?->ipv4_address ?? '-' }}</strong></div>
                        <div class="small mt-1">&nbsp;</div>
                    </div>
                </div>
            </div>

            {{-- Action buttons --}}
            <div class="mb-3">
                <button class="btn btn-info btn-sm" data-ajax-post="{{ route('ppp-users.add-invoice', $pppUser) }}" data-confirm="Tambah tagihan baru untuk pelanggan ini?">
                    <i class="fas fa-file-invoice mr-1"></i>add invoice
                </button>
                <button class="btn btn-success btn-sm ml-1 btn-disconnect" data-url="{{ route('ppp-users.disconnect', $pppUser) }}" {{ $activeSession ? '' : 'disabled' }}>
                    <i class="fas fa-ban mr-1"></i>disconnect
                </button>
                <button class="btn btn-danger btn-sm ml-1 btn-toggle-akun" data-url="{{ route('ppp-users.toggle-status', $pppUser) }}" data-status="{{ $pppUser->status_akun }}">
                    <i class="fas fa-times mr-1"></i>{{ $pppUser->status_akun === 'disable' ? 'enable' : 'disable' }}
                </button>
            </div>

            {{-- Invoice datatable --}}
            <table id="invoice-dt" class="table table-striped table-hover table-sm" style="width:100%">
                <thead class="thead-light">
                    <tr>
                        <th>Id</th>
                        <th>Invoice</th>
                        <th>Paket Langganan</th>
                        <th>Jumlah</th>
                        <th>Aktivasi</th>
                        <th>Deadline</th>
                        <th>Owner Data</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>{{-- end main-invoice-pane --}}

        {{-- TAB: RIWAYAT DIALUP --}}
        <div class="mf-tab-pane" id="main-dialup-pane" style="display:none;padding:1.1rem 1.25rem;">
            <p class="text-muted small"><strong>*** only display last 100 record</strong></p>
            <table id="dialup-dt" class="table table-striped table-hover table-sm" style="width:100%">
                <thead class="thead-light">
                    <tr>
                        <th>Acct ID</th>
                        <th>Uptime</th>
                        <th>Waktu Mulai</th>
                        <th>Waktu Berakhir</th>
                        <th>NAS</th>
                        <th>Upload</th>
                        <th>Download</th>
                        <th>Terminate By</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>{{-- end main-dialup-pane --}}

        {{-- TAB: CPE PERANGKAT --}}
        <div class="mf-tab-pane" id="main-cpe-pane" style="display:none;padding:1.1rem 1.25rem;">
            @include('cpe._panel', ['pppUser' => $pppUser])
        </div>{{-- end main-cpe-pane --}}

    </div>{{-- end mf-section --}}

</div>{{-- end mf-page --}}

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

        // ── mf-tab switching ──
        (function () {
            function setupTabs(navId) {
                var nav = document.getElementById(navId);
                if (!nav) return;
                nav.querySelectorAll('.mf-tab-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        nav.querySelectorAll('.mf-tab-btn').forEach(function (b) { b.classList.remove('mf-tab-active'); });
                        // Hide all sibling panes of the same group
                        var target = document.getElementById(btn.dataset.target);
                        if (!target) return;
                        // find all panes that are siblings (same parent container)
                        var paneParent = target.parentElement;
                        paneParent.querySelectorAll(':scope > .mf-tab-pane').forEach(function (p) { p.style.display = 'none'; });
                        btn.classList.add('mf-tab-active');
                        target.style.display = 'block';

                        // DataTable lazy init
                        if (btn.dataset.target === 'main-invoice-pane' && !invoiceDtInitialized) {
                            invoiceDtInitialized = true;
                            $('#invoice-dt').DataTable({
                                processing: true, serverSide: true, responsive: true,
                                ajax: '{{ route('ppp-users.invoice-datatable', $pppUser) }}',
                                columns: [
                                    { data: 'id' }, { data: 'invoice_number' }, { data: 'paket_langganan' },
                                    { data: 'total' }, { data: 'created_at' }, { data: 'due_date' },
                                    { data: 'owner' }, { data: 'aksi', orderable: false, searchable: false },
                                ],
                                language: {
                                    search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data',
                                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                                    infoEmpty: 'Tidak ada data', zeroRecords: 'Tidak ada data.',
                                    emptyTable: 'Belum ada invoice.', processing: 'Memuat...',
                                    paginate: { first: 'Pertama', last: 'Terakhir', next: 'Selanjutnya', previous: 'Sebelumnya' },
                                },
                                order: [[0, 'desc']], pageLength: 10,
                            });
                        }
                        if (btn.dataset.target === 'main-dialup-pane' && !dialupDtInitialized) {
                            dialupDtInitialized = true;
                            $('#dialup-dt').DataTable({
                                processing: true, serverSide: true, responsive: true,
                                ajax: '{{ route('ppp-users.dialup-datatable', $pppUser) }}',
                                columns: [
                                    { data: 'radacctid' }, { data: 'uptime' }, { data: 'start' }, { data: 'stop' },
                                    { data: 'nas' }, { data: 'upload' }, { data: 'download' }, { data: 'terminate' },
                                ],
                                language: {
                                    search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data',
                                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                                    infoEmpty: 'Tidak ada data', zeroRecords: 'Tidak ada data.',
                                    emptyTable: 'Belum ada riwayat dialup.', processing: 'Memuat...',
                                    paginate: { first: 'Pertama', last: 'Terakhir', next: 'Selanjutnya', previous: 'Sebelumnya' },
                                },
                                order: [[0, 'desc']], pageLength: 10,
                            });
                        }
                        if (btn.dataset.target === 'main-cpe-pane') {
                            var $btn = $('#btn-cpe-refresh');
                            if ($btn.length) {
                                var id = $btn.data('ppp-user-id');
                                $.ajax({
                                    method: 'GET',
                                    url: '/ppp-users/' + id + '/cpe/refresh-cache',
                                    success: function (res) { if (res.device) updateCpePanel(res.device); },
                                });
                            }
                        }
                        // Invalidate leaflet map if info tab shown
                        if (btn.dataset.target === 'inner-info-pane' && isMapVisible) {
                            ensureLocationMapReady();
                            syncMapFromInputs();
                        }
                    });
                });
            }
            setupTabs('mainTabNav');
            setupTabs('innerTabNav');
        })();

        // Live uptime counter
        (function () {
            var el = document.getElementById('uptime-counter');
            if (!el) return;
            var base = {{ $baseSeconds }};
            if (base <= 0) return;
            function fmt(s) {
                var d = Math.floor(s / 86400);
                var h = Math.floor((s % 86400) / 3600);
                var m = Math.floor((s % 3600) / 60);
                var sec = s % 60;
                if (d > 0) return d + 'd ' + h + 'h ' + m + 'm ' + sec + 's';
                if (h > 0) return h + 'h ' + m + 'm ' + sec + 's';
                return m + 'm ' + sec + 's';
            }
            var t = base;
            el.textContent = fmt(t);
            setInterval(function () { el.textContent = fmt(++t); }, 1000);
        })();

        // Live sync timer
        (function () {
            var el = document.getElementById('sync-timer');
            if (!el) return;
            var ts = parseInt(el.getAttribute('data-ts'), 10);
            function fmtAgo(s) {
                if (s < 60) return 'baru saja';
                if (s < 3600) return Math.floor(s / 60) + ' menit yang lalu';
                if (s < 86400) return Math.floor(s / 3600) + ' jam yang lalu';
                return Math.floor(s / 86400) + ' hari yang lalu';
            }
            function update() {
                var diff = Math.floor(Date.now() / 1000) - ts;
                el.textContent = fmtAgo(diff);
            }
            update();
            setInterval(update, 60000);
        })();

        const ipSelect = document.getElementById('tipe-ip-select');
        const staticSection = document.getElementById('static-ip-section');
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
            if (! locationMapInfo) {
                return;
            }

            locationMapInfo.textContent = message;
            locationMapInfo.className = className;
        }

        function initLocationMap() {
            if (locationMap || typeof L === 'undefined') {
                return;
            }

            const mapContainer = document.getElementById('ppp-location-map');

            if (! mapContainer) {
                return;
            }

            const initialLat = parseCoordinate(latitudeInput?.value);
            const initialLng = parseCoordinate(longitudeInput?.value);
            const initialPoint = (initialLat !== null && initialLng !== null) ? [initialLat, initialLng] : [-7.36, 109.90];
            const initialZoom = (initialLat !== null && initialLng !== null) ? earthFocusZoom : 12;

            locationMap = L.map('ppp-location-map').setView(initialPoint, initialZoom);

            const earthLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 19,
                maxNativeZoom: earthFocusZoom,
                attribution: 'Tiles &copy; Esri'
            }).addTo(locationMap);

            const streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            });

            L.control.layers({
                Earth: earthLayer,
                Street: streetLayer
            }).addTo(locationMap);

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

            if (locationMapWrapper) {
                locationMapWrapper.classList.toggle('d-none', ! visible);
            }

            if (toggleMapButton) {
                toggleMapButton.innerHTML = visible
                    ? '<i class="fas fa-eye-slash mr-1"></i>Sembunyikan Maps'
                    : '<i class="fas fa-map-marked-alt mr-1"></i>Lihat Maps';
            }

            if (! visible) {
                return;
            }

            ensureLocationMapReady();
            syncMapFromInputs();
        }

        function ensureLocationMapReady() {
            initLocationMap();

            if (locationMap) {
                setTimeout(function () {
                    locationMap.invalidateSize();
                }, 0);
            }
        }

        function getFocusZoom() {
            if (! locationMap) {
                return earthFocusZoom;
            }

            const maxZoom = locationMap.getMaxZoom();

            if (typeof maxZoom === 'number' && Number.isFinite(maxZoom)) {
                return Math.min(maxZoom, earthFocusZoom);
            }

            return earthFocusZoom;
        }

        function setMapPoint(lat, lng, shouldFocusMap) {
            if (latitudeInput) {
                latitudeInput.value = lat.toFixed(7);
            }
            if (longitudeInput) {
                longitudeInput.value = lng.toFixed(7);
            }
            if (locationMarker) {
                locationMarker.setLatLng([lat, lng]);
            }
            if (shouldFocusMap && locationMap) {
                locationMap.setView([lat, lng], getFocusZoom());
            }

            setMapInfo('Titik disetel: ' + lat.toFixed(7) + ', ' + lng.toFixed(7), 'text-success d-block mb-3');
        }

        function setLocationMeta(accuracy, method) {
            if (locationAccuracyInput) {
                locationAccuracyInput.value = accuracy.toFixed(2);
            }
            if (locationMethodInput) {
                locationMethodInput.value = method;
            }
            if (locationCapturedAtInput) {
                locationCapturedAtInput.value = new Date().toISOString();
            }
            if (locationMetaInfo) {
                locationMetaInfo.textContent = 'Akurasi: ' + accuracy.toFixed(1) + ' m (' + method + ')';
                locationMetaInfo.classList.remove('text-muted');
                locationMetaInfo.classList.add('text-success');
            }
        }

        function captureGpsSamples() {
            if (!navigator.geolocation) {
                alert('Browser tidak mendukung geolocation.');
                return;
            }

            captureGpsButton.disabled = true;
            captureGpsButton.innerHTML = '<i class=\"fas fa-spinner fa-spin mr-1\"></i>Mengambil titik...';
            const samples = [];

            function takeSample() {
                navigator.geolocation.getCurrentPosition(function (position) {
                    samples.push({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy
                    });

                    if (samples.length < 3) {
                        setTimeout(takeSample, 1200);
                        return;
                    }

                    const latitudes = samples.map(s => s.latitude);
                    const longitudes = samples.map(s => s.longitude);
                    const accuracies = samples.map(s => s.accuracy);
                    const latitudeMedian = median(latitudes);
                    const longitudeMedian = median(longitudes);

                    setMapVisibility(true);
                    ensureLocationMapReady();
                    setMapPoint(latitudeMedian, longitudeMedian, true);
                    setLocationMeta(median(accuracies), 'gps');

                    captureGpsButton.disabled = false;
                    captureGpsButton.innerHTML = '<i class=\"fas fa-location-arrow mr-1\"></i>Ambil Titik GPS (3 Sampel)';
                }, function (error) {
                    let message = 'Gagal mengambil lokasi.';
                    if (error.code === 1) message = 'Izin lokasi ditolak.';
                    if (error.code === 2) message = 'Lokasi tidak tersedia.';
                    if (error.code === 3) message = 'Permintaan lokasi timeout.';
                    alert(message);
                    captureGpsButton.disabled = false;
                    captureGpsButton.innerHTML = '<i class=\"fas fa-location-arrow mr-1\"></i>Ambil Titik GPS (3 Sampel)';
                }, {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0,
                });
            }

            takeSample();
        }

        if (captureGpsButton) {
            captureGpsButton.addEventListener('click', captureGpsSamples);
        }

        if (toggleMapButton) {
            toggleMapButton.addEventListener('click', function () {
                setMapVisibility(! isMapVisible);
            });
        }

        function syncMapFromInputs() {
            if (! isMapVisible) {
                return;
            }

            const latitude = parseCoordinate(latitudeInput?.value);
            const longitude = parseCoordinate(longitudeInput?.value);

            if (latitude === null || longitude === null) {
                return;
            }

            ensureLocationMapReady();
            setMapPoint(latitude, longitude, true);
        }

        if (latitudeInput) {
            latitudeInput.addEventListener('change', syncMapFromInputs);
        }

        if (longitudeInput) {
            longitudeInput.addEventListener('change', syncMapFromInputs);
        }

        setMapVisibility(false);

        function toggleStatic() {
            staticSection.style.display = ipSelect.value === 'static' ? 'block' : 'none';
        }
        ipSelect.addEventListener('change', toggleStatic);
        toggleStatic();

        var invoiceDtInitialized = false;
        var dialupDtInitialized  = false;

        // Disconnect button
        $(document).on('click', '.btn-disconnect', function () {
            var url = $(this).data('url');
            if (!confirm('Putuskan koneksi aktif pelanggan ini?')) return;
            var btn = $(this);
            btn.prop('disabled', true);
            $.post(url, { _token: '{{ csrf_token() }}' })
                .done(function (res) {
                    window.AppAjax.showToast(res.status || 'Koneksi diputus.', 'success');
                    setTimeout(function () { location.reload(); }, 1500);
                })
                .fail(function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.status) ? xhr.responseJSON.status : 'Gagal memutus koneksi.';
                    window.AppAjax.showToast(msg, 'danger');
                    btn.prop('disabled', false);
                });
        });

        // Toggle akun (enable/disable)
        $(document).on('click', '.btn-toggle-akun', function () {
            var btn = $(this);
            var url = btn.data('url');
            var current = btn.data('status');
            var action = current === 'disable' ? 'enable' : 'disable';
            if (!confirm('Ubah status akun menjadi ' + action + '?')) return;
            $.post(url, { _token: '{{ csrf_token() }}' })
                .done(function (res) {
                    window.AppAjax.showToast('Status akun: ' + res.status, 'success');
                    btn.data('status', res.status);
                    btn.html('<i class="fas fa-times mr-1"></i>' + (res.status === 'disable' ? 'enable' : 'disable'));
                })
                .fail(function () { window.AppAjax.showToast('Gagal mengubah status.', 'danger'); });
        });
    </script>

    {{-- CPE Management Scripts --}}
    <script>
    (function () {
        var csrfToken = '{{ csrf_token() }}';

        function cpeAjax(method, url, data, onSuccess, onComplete) {
            $.ajax({
                method: method,
                url: url,
                data: Object.assign({ _token: csrfToken }, data || {}),
                dataType: 'json',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                success: function (res) {
                    if (res.message) window.AppAjax.showToast(res.message, 'success');
                    if (onSuccess) onSuccess(res);
                },
                error: function (xhr) {
                    var msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error))
                        || 'Terjadi kesalahan. Status: ' + xhr.status;
                    window.AppAjax.showToast(msg, 'danger');
                },
                complete: function () {
                    if (onComplete) onComplete();
                },
            });
        }

        function updateCpePanel(device) {
            var timeStr = device.last_seen_at || '';
            var statusHtml = {
                online:  '<span class="badge badge-success">Online</span>' + (timeStr ? ' <span class="text-muted small">· ' + timeStr + '</span>' : ''),
                offline: '<span class="badge badge-danger">Offline</span>' + (timeStr ? ' <span class="text-muted small">· ' + timeStr + '</span>' : ''),
                unknown: '<span class="badge badge-secondary">Tidak Diketahui</span>',
            };
            $('#cpe-status').html(statusHtml[device.status] || statusHtml.unknown);

            // PPPoE status
            var pppoeHtml = device.pppoe_online
                ? '<span class="badge badge-success">Online</span>' + (device.pppoe_ip ? ' <span class="text-muted small">· ' + device.pppoe_ip + '</span>' : '')
                : '<span class="badge badge-danger">Offline</span>';
            $('#cpe-pppoe-status').html(pppoeHtml);
            $('#cpe-manufacturer').text(device.manufacturer || '-');
            $('#cpe-model').text(device.model || '-');
            $('#cpe-firmware').text(device.firmware_version || '-');
            $('#cpe-serial').text(device.serial_number || '-');
            $('#cpe-device-id').text(device.genieacs_device_id || '-');

            // OLT signal
            var $sig = $('#cpe-olt-signal');
            if (device.olt_rx_dbm !== null && device.olt_rx_dbm !== undefined) {
                var rxVal  = parseFloat(device.olt_rx_dbm).toFixed(2);
                var rxCls  = parseFloat(rxVal) < -27 ? 'text-danger' : 'text-success';
                var distTxt = device.olt_distance_m ? ' <span class="text-muted small">· ' + parseInt(device.olt_distance_m).toLocaleString() + ' m</span>' : '';
                var sigHtml = '<a href="#" id="cpe-olt-signal-link" data-onu-optic-id="' + (device.olt_onu_optic_id || '') + '" data-ppp-user-id="' + device.id + '">'
                    + '<span class="' + rxCls + '">' + rxVal + ' dBm</span>' + distTxt
                    + ' <i class="fas fa-chart-line ml-1 text-muted small"></i></a>';
                $sig.html(sigHtml);
            } else {
                $sig.html('<span class="text-muted">-</span>');
            }

            // Update multi-SSID accordion
            var params = device.cached_params || {};
            if (params.wifi_networks && params.wifi_networks.length > 0) {
                $('#cpe-wifi-empty').hide();
                $('#cpe-wifi-count').text(params.wifi_networks.length + ' jaringan terdeteksi');
                $.each(params.wifi_networks, function (i, wn) {
                    var $card = $('#cpe-wifi-card-' + wn.index);
                    if ($card.length) {
                        // Update values in existing card
                        $card.find('.cpe-wifi-ssid-val').val(wn.ssid || '');
                        $card.find('.cpe-wifi-pass-val').val(wn.password || '');
                        $card.find('.cpe-wifi-enable-val').prop('checked', !!wn.enabled);
                        $card.find('.cpe-wifi-channel-val').val(
                            wn.channel !== null && wn.channel !== undefined ? String(wn.channel) : '0'
                        );
                        $card.find('.cpe-wifi-status-' + wn.index)
                            .removeClass('badge-success badge-danger')
                            .addClass(wn.enabled ? 'badge-success' : 'badge-danger')
                            .text(wn.enabled ? 'Aktif' : 'Nonaktif');
                    } else {
                        // New card — reload page to render Blade
                        location.reload();
                    }
                });
            }

            // Update WAN accordion count
            if (params.wan_connections && params.wan_connections.length > 0) {
                $('#cpe-wan-empty').hide();
                $('#cpe-wan-count').text(params.wan_connections.length + ' koneksi terdeteksi');
                $.each(params.wan_connections, function (i, wc) {
                    var safeKey = wc.key.replace(/\./g, '_');
                    var $card   = $('#cpe-wan-card-' + safeKey);
                    if ($card.length) {
                        $card.find('.cpe-wan-conn-type').val(wc.connection_type || '');
                        $card.find('.cpe-wan-vlan').val(wc.vlan_id || '');
                        $card.find('.cpe-wan-vlan-prio').val(wc.vlan_prio || 0);
                        $card.find('.cpe-wan-dns').val(wc.dns_servers || '');
                        $card.find('.cpe-wan-user').val(wc.username || '');
                        $card.find('.cpe-wan-enable').prop('checked', !!wc.enabled);
                    }
                });
            }
        }

        // OLT History modal
        $(document).on('click', '#cpe-olt-signal-link', function (e) {
            e.preventDefault();
            var pppUserId = $(this).data('ppp-user-id');
            if (!pppUserId) return;
            $('#cpe-olt-history-loading').show();
            $('#cpe-olt-history-content').hide();
            $('#cpe-olt-history-modal').modal('show');
            $.get('/ppp-users/' + pppUserId + '/cpe/olt-history', function (res) {
                $('#cpe-olt-history-loading').hide();
                var histories = (res.data && res.data.histories) ? res.data.histories : [];
                if (histories.length === 0) {
                    $('#cpe-olt-history-tbody').html('');
                    $('#cpe-olt-history-empty').show();
                } else {
                    $('#cpe-olt-history-empty').hide();
                    var rows = '';
                    var prevRx = null;
                    $.each(histories, function (i, h) {
                        var rx = h.rx_onu_dbm !== null ? parseFloat(h.rx_onu_dbm).toFixed(2) : '-';
                        var rxCls = (rx !== '-' && parseFloat(rx) < -27) ? 'text-danger' : '';
                        var delta = '';
                        if (prevRx !== null && rx !== '-') {
                            var diff = parseFloat(rx) - prevRx;
                            if (Math.abs(diff) >= 0.5) {
                                delta = diff > 0
                                    ? ' <small class="text-success">▲' + Math.abs(diff).toFixed(2) + '</small>'
                                    : ' <small class="text-danger">▼' + Math.abs(diff).toFixed(2) + '</small>';
                            }
                        }
                        if (rx !== '-') prevRx = parseFloat(rx);
                        var dist = h.distance_m !== null ? parseInt(h.distance_m).toLocaleString() : '-';
                        var status = h.status || '-';
                        var polledAt = h.polled_at ? h.polled_at.replace('T', ' ').substring(0, 19) : '-';
                        rows += '<tr>'
                            + '<td class="small">' + polledAt + '</td>'
                            + '<td class="' + rxCls + '">' + rx + (rx !== '-' ? ' dBm' : '') + delta + '</td>'
                            + '<td>' + dist + '</td>'
                            + '<td><small>' + status + '</small></td>'
                            + '</tr>';
                    });
                    $('#cpe-olt-history-tbody').html(rows);
                }
                $('#cpe-olt-history-content').show();
            }).fail(function () {
                $('#cpe-olt-history-loading').hide();
                $('#cpe-olt-history-content').show();
                $('#cpe-olt-history-tbody').html('');
                $('#cpe-olt-history-empty').text('Gagal memuat data.').show();
            });
        });

        // OLT Link modal: search ONU
        var oltLinkSearchTimer = null;
        $('#cpe-olt-link-modal').on('show.bs.modal', function () {
            $('#cpe-olt-link-search').val('');
            $('#cpe-olt-link-list').html('<p class="text-muted small text-center py-2">Ketik untuk mencari ONU.</p>');
        });
        $(document).on('input', '#cpe-olt-link-search', function () {
            clearTimeout(oltLinkSearchTimer);
            var q = $(this).val().trim();
            var pppUserId = {{ $pppUser->id }};
            oltLinkSearchTimer = setTimeout(function () {
                $('#cpe-olt-link-loading').show();
                $.get('/ppp-users/' + pppUserId + '/cpe/olt-onus', { q: q }, function (res) {
                    $('#cpe-olt-link-loading').hide();
                    if (!res.data || res.data.length === 0) {
                        $('#cpe-olt-link-list').html('<p class="text-muted small text-center py-2">Tidak ada ONU ditemukan.</p>');
                        return;
                    }
                    var html = '<div class="list-group list-group-flush">';
                    $.each(res.data, function (i, onu) {
                        var rx = onu.rx_onu_dbm !== null ? parseFloat(onu.rx_onu_dbm).toFixed(2) + ' dBm' : '-';
                        var rxCls = onu.rx_onu_dbm !== null && parseFloat(onu.rx_onu_dbm) < -27 ? 'text-danger' : 'text-success';
                        html += '<a href="#" class="list-group-item list-group-item-action py-2 cpe-olt-link-select" data-optic-id="' + onu.id + '" data-onu-name="' + onu.onu_name + '">'
                            + '<div class="d-flex justify-content-between align-items-center">'
                            + '<div><strong class="small">' + (onu.onu_name || '-') + '</strong>'
                            + '<br><small class="text-muted">' + (onu.serial_number || '') + '</small></div>'
                            + '<span class="' + rxCls + ' small">' + rx + '</span>'
                            + '</div></a>';
                    });
                    html += '</div>';
                    $('#cpe-olt-link-list').html(html);
                }).fail(function () {
                    $('#cpe-olt-link-loading').hide();
                    $('#cpe-olt-link-list').html('<p class="text-danger small text-center py-2">Gagal memuat data.</p>');
                });
            }, 300);
        });

        // OLT Link: pilih ONU
        $(document).on('click', '.cpe-olt-link-select', function (e) {
            e.preventDefault();
            var opticId  = $(this).data('optic-id');
            var onuName  = $(this).data('onu-name');
            var pppUserId = {{ $pppUser->id }};
            if (!confirm('Link ONU "' + onuName + '" ke modem ini?')) return;
            $.ajax({
                method: 'POST',
                url: '/ppp-users/' + pppUserId + '/cpe/olt-link',
                data: { _token: csrfToken, olt_onu_optic_id: opticId },
                success: function (res) {
                    $('#cpe-olt-link-modal').modal('hide');
                    window.AppAjax.showToast(res.message || 'ONU berhasil di-link.', 'success');
                    setTimeout(function () { location.reload(); }, 800);
                },
                error: function () {
                    window.AppAjax.showToast('Gagal menyimpan link ONU.', 'danger');
                }
            });
        });

        // OLT Unlink
        $(document).on('click', '#cpe-olt-unlink-btn', function (e) {
            e.preventDefault();
            var pppUserId = $(this).data('ppp-user-id');
            if (!confirm('Hapus link manual ONU dari modem ini?')) return;
            $.ajax({
                method: 'POST',
                url: '/ppp-users/' + pppUserId + '/cpe/olt-link',
                data: { _token: csrfToken, olt_onu_optic_id: null },
                success: function (res) {
                    window.AppAjax.showToast(res.message || 'Link ONU dihapus.', 'success');
                    setTimeout(function () { location.reload(); }, 800);
                },
                error: function () {
                    window.AppAjax.showToast('Gagal menghapus link ONU.', 'danger');
                }
            });
        });

        // Sync
        $(document).on('click', '#btn-cpe-sync', function () {
            var $btn = $(this);
            var id   = $btn.data('ppp-user-id');
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Mencari...');
            $.ajax({
                method: 'POST',
                url: '/ppp-users/' + id + '/cpe/sync',
                data: { _token: csrfToken },
                success: function (res) {
                    if (res.success) {
                        window.AppAjax.showToast(res.message || 'Perangkat ditemukan!', 'success');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        window.AppAjax.showToast(res.message || 'Perangkat tidak ditemukan.', 'warning');
                        $btn.prop('disabled', false).html('<i class="fas fa-search mr-1"></i> Cari / Sinkronisasi GenieACS');
                    }
                },
                error: function (xhr) {
                    window.AppAjax.showToast(xhr.responseJSON?.message || 'Gagal menghubungi server. Status: ' + xhr.status, 'danger');
                    $btn.prop('disabled', false).html('<i class="fas fa-search mr-1"></i> Cari / Sinkronisasi GenieACS');
                },
            });
        });

        // Refresh — 2 fase: cache dulu (cepat), lalu sinkron ke modem (background)
        $(document).on('click', '#btn-cpe-refresh', function () {
            var $btn = $(this);
            var id   = $btn.data('ppp-user-id');
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Memuat...');

            // Fase 1: tampilkan data cache GenieACS langsung (~200ms)
            $.ajax({
                method: 'GET',
                url: '/ppp-users/' + id + '/cpe/refresh-cache',
                success: function (res) {
                    if (res.device) updateCpePanel(res.device);
                    $btn.html('<i class="fas fa-sync-alt fa-spin mr-1"></i> Sinkron ke Modem...');
                },
                complete: function () {
                    // Fase 2: sinkron langsung ke modem via connection_request (3-15s)
                    $.ajax({
                        method: 'POST',
                        url: '/ppp-users/' + id + '/cpe/refresh',
                        data: { _token: csrfToken },
                        success: function (res) {
                            if (res.device) updateCpePanel(res.device);
                            window.AppAjax.showToast('Info perangkat diperbarui.', 'success');
                        },
                        error: function (xhr) {
                            window.AppAjax.showToast(xhr.responseJSON?.message || 'Gagal refresh dari modem.', 'warning');
                        },
                        complete: function () {
                            $btn.prop('disabled', false).html('<i class="fas fa-sync-alt mr-1"></i> Refresh Info');
                        },
                    });
                },
            });
        });

        // Reboot
        $(document).on('click', '#btn-cpe-reboot', function () {
            if (!confirm('Yakin ingin mereboot perangkat ini?')) return;
            var $btn = $(this);
            var id = $btn.data('ppp-user-id');
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Mereboot...');
            cpeAjax('POST', '/ppp-users/' + id + '/cpe/reboot', {}, null, function () {
                $btn.prop('disabled', false).html('<i class="fas fa-power-off mr-1"></i> Reboot Perangkat');
            });
        });

        // WiFi per-index (multi-SSID)
        $(document).on('click', '.btn-cpe-wifi-save', function () {
            var $btn    = $(this);
            var id      = $btn.data('ppp-user-id');
            var wlanIdx = $btn.data('wlan-idx');
            var $card   = $('#cpe-wifi-card-' + wlanIdx);
            var ssid    = $card.find('.cpe-wifi-ssid-val').val().trim();
            var pass    = $card.find('.cpe-wifi-pass-val').val().trim();
            var enabled = $card.find('.cpe-wifi-enable-val').is(':checked') ? 1 : 0;
            var channel = $card.find('.cpe-wifi-channel-val').val();
            if (pass && pass.length < 8) { window.AppAjax.showToast('Password WiFi minimal 8 karakter.', 'warning'); return; }
            var payload = { enabled: enabled };
            if (ssid)                                    payload.ssid     = ssid;
            if (pass)                                    payload.password = pass;
            if (channel !== undefined && channel !== '') payload.channel  = parseInt(channel, 10);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>');
            cpeAjax('POST', '/ppp-users/' + id + '/cpe/wifi/' + wlanIdx, payload, null, function () {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
            });
        });

        // MAC address edit
        $(document).on('click', '#cpe-mac-edit-btn', function (e) {
            e.preventDefault();
            $('#cpe-mac-display').addClass('d-none');
            $('#cpe-mac-form').removeClass('d-none');
            $('#cpe-mac-input').focus();
        });
        $(document).on('click', '#cpe-mac-cancel-btn', function () {
            $('#cpe-mac-form').addClass('d-none');
            $('#cpe-mac-display').removeClass('d-none');
        });
        $(document).on('click', '#cpe-mac-save-btn', function () {
            var id  = $('#cpe-mac-input').data('ppp-user-id');
            var mac = $('#cpe-mac-input').val().trim();
            if (!mac) return;
            cpeAjax('POST', '/ppp-users/' + id + '/cpe/mac', { mac_address: mac }, function (res) {
                $('#cpe-mac-val').text(mac);
                $('#cpe-mac-form').addClass('d-none');
                $('#cpe-mac-display').removeClass('d-none');
            });
        });

        // WAN save
        $(document).on('click', '.btn-cpe-wan-save', function () {
            var $btn   = $(this);
            var id     = $btn.data('ppp-user-id');
            var wanKey = $btn.data('wan-key');
            var parts  = wanKey.split('.');  // [wanIdx, cdIdx, connIdx]
            var $card  = $('#cpe-wan-card-' + wanKey.replace(/\./g, '_'));

            // Collect port binding checkboxes
            var ifaces = [];
            $card.find('.cpe-wan-iface-cb:checked').each(function () {
                ifaces.push($(this).data('iface'));
            });

            var payload = {
                enabled:         $card.find('.cpe-wan-enable').is(':checked') ? 1 : 0,
                connection_type: $card.find('.cpe-wan-conn-type').val(),
                vlan_id:         $card.find('.cpe-wan-vlan').val() || null,
                vlan_prio:       $card.find('.cpe-wan-vlan-prio').val() || 0,
                dns_servers:     $card.find('.cpe-wan-dns').val().trim() || null,
                lan_interface:   ifaces.join(','),
            };
            var user = $card.find('.cpe-wan-user').val().trim();
            var pass = $card.find('.cpe-wan-pass').val().trim();
            if (user) payload.username = user;
            if (pass) payload.password = pass;

            if (!confirm('Terapkan perubahan konfigurasi WAN ke modem?')) return;
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>');

            var url = '/ppp-users/' + id + '/cpe/wan/' + parts[0] + '/' + parts[1] + '/' + parts[2];
            $.ajax({
                method: 'PUT',
                url: url,
                data: Object.assign({ _token: csrfToken }, payload),
                success: function (res) {
                    if (res.message) window.AppAjax.showToast(res.message, 'success');
                },
                error: function (xhr) {
                    window.AppAjax.showToast((xhr.responseJSON && xhr.responseJSON.message) || 'Gagal menyimpan. Status: ' + xhr.status, 'danger');
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
                },
            });
        });

        // Unlink
        $(document).on('click', '#btn-cpe-unlink', function () {
            if (!confirm('Lepaskan tautan perangkat? Data lokal akan dihapus.')) return;
            var id = $(this).data('ppp-user-id');
            cpeAjax('DELETE', '/ppp-users/' + id + '/cpe', {}, function (res) {
                if (res.success) location.reload();
            });
        });

        // Cek Trafik — polling real-time
        var cpeTrafficTimer = null;
        var cpeTrafficChart = null;
        var cpeChartLabels  = [];
        var cpeChartRx      = [];
        var cpeChartTx      = [];
        var CPE_CHART_MAX   = 40; // titik data maksimal

        function cpeFormatBits(bps) {
            if (bps >= 1000000) return (bps / 1000000).toFixed(2) + ' Mbps';
            if (bps >= 1000)    return (bps / 1000).toFixed(1) + ' Kbps';
            return bps + ' bps';
        }

        function cpeFormatBytes(b) {
            if (b >= 1073741824) return (b / 1073741824).toFixed(2) + ' GB';
            if (b >= 1048576)    return (b / 1048576).toFixed(1) + ' MB';
            if (b >= 1024)       return (b / 1024).toFixed(1) + ' KB';
            return b + ' B';
        }

        function cpeInitChart() {
            var ctx = document.getElementById('cpe-traffic-chart');
            if (!ctx) return;
            cpeChartLabels = [];
            cpeChartRx     = [];
            cpeChartTx     = [];
            if (cpeTrafficChart) { cpeTrafficChart.destroy(); }
            cpeTrafficChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: cpeChartLabels,
                    datasets: [
                        {
                            label: 'RX',
                            data: cpeChartRx,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40,167,69,0.1)',
                            borderWidth: 1.5,
                            pointRadius: 0,
                            fill: true,
                            tension: 0.3,
                        },
                        {
                            label: 'TX',
                            data: cpeChartTx,
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0,123,255,0.1)',
                            borderWidth: 1.5,
                            pointRadius: 0,
                            fill: true,
                            tension: 0.3,
                        },
                    ],
                },
                options: {
                    animation: false,
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: { display: false },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (v) { return cpeFormatBits(v); },
                                maxTicksLimit: 4,
                                font: { size: 10 },
                            },
                        },
                    },
                    plugins: {
                        legend: { display: true, labels: { boxWidth: 12, font: { size: 11 } } },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    return ctx.dataset.label + ': ' + cpeFormatBits(ctx.parsed.y);
                                },
                            },
                        },
                    },
                },
            });
        }

        function cpePushChart(rxBps, txBps) {
            var now = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            cpeChartLabels.push(now);
            cpeChartRx.push(rxBps);
            cpeChartTx.push(txBps);
            if (cpeChartLabels.length > CPE_CHART_MAX) {
                cpeChartLabels.shift();
                cpeChartRx.shift();
                cpeChartTx.shift();
            }
            if (cpeTrafficChart) cpeTrafficChart.update();
        }

        function cpeStopTraffic() {
            if (cpeTrafficTimer) {
                clearInterval(cpeTrafficTimer);
                cpeTrafficTimer = null;
            }
            if (cpeTrafficChart) {
                cpeTrafficChart.destroy();
                cpeTrafficChart = null;
            }
            $('#cpe-traffic-panel').hide();
            $('#btn-cpe-traffic').html('<i class="fas fa-tachometer-alt mr-1"></i> Cek Trafik');
        }

        function cpeFetchTraffic(pppUserId) {
            $.ajax({
                method: 'GET',
                url: '/ppp-users/' + pppUserId + '/cpe/traffic',
                success: function (res) {
                    $('#cpe-traffic-loading').hide();
                    if (!res.is_active) {
                        $('#cpe-traffic-data').hide();
                        $('#cpe-traffic-offline').show();
                        return;
                    }
                    $('#cpe-traffic-offline').hide();
                    $('#cpe-traffic-data').show();
                    $('#cpe-traffic-queue').text(res.queue_name || '');
                    $('#cpe-traffic-rx').text(cpeFormatBits(res.rx));
                    $('#cpe-traffic-tx').text(cpeFormatBits(res.tx));
                    $('#cpe-traffic-bytes-in').text(cpeFormatBytes(res.bytes_in));
                    $('#cpe-traffic-bytes-out').text(cpeFormatBytes(res.bytes_out));
                    cpePushChart(res.rx, res.tx);
                },
                error: function () {
                    $('#cpe-traffic-loading').hide();
                    $('#cpe-traffic-data').hide();
                    $('#cpe-traffic-offline').show().html('<i class="fas fa-exclamation-triangle text-warning mr-1"></i> Gagal mengambil data trafik');
                },
            });
        }

        $(document).on('click', '#btn-cpe-traffic', function () {
            var id = $(this).data('ppp-user-id');
            if (cpeTrafficTimer) {
                cpeStopTraffic();
                return;
            }
            $('#cpe-traffic-loading').show();
            $('#cpe-traffic-data').hide();
            $('#cpe-traffic-offline').hide();
            $('#cpe-traffic-panel').show();
            $(this).html('<i class="fas fa-stop mr-1"></i> Stop Trafik');
            cpeInitChart();
            cpeFetchTraffic(id);
            cpeTrafficTimer = setInterval(function () { cpeFetchTraffic(id); }, 3000);
        });

        // Auto-stop saat tab CPE di-hide
        $('a[href="#main-cpe-pane"]').on('hide.bs.tab', function () {
            cpeStopTraffic();
        });
    }());
    </script>
@endpush
