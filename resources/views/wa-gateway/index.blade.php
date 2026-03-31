@extends('layouts.admin')

@section('title', 'WA Gateway')

@section('content')
@php
    $isSelfHostedApp = (bool) ($isSelfHostedApp ?? false);
    $activeTab = request()->query('tab', 'overview');
    if (! in_array($activeTab, ['overview', 'devices', 'keyword-rules'], true)) {
        $activeTab = 'overview';
    }
    $canAccessWaBlast = auth()->user()->isSuperAdmin() || in_array(auth()->user()->role, ['administrator', 'noc', 'it_support'], true);
    $waDevicesTabUrl = route('wa-gateway.index', array_filter([
        'tab' => 'devices',
        'tenant_id' => auth()->user()->isSuperAdmin() && ! $isSelfHostedApp ? ($selectedTenant?->id ?? null) : null,
    ]));
@endphp

@if(auth()->user()->isSuperAdmin() && ! $isSelfHostedApp)
<div class="row mb-3">
    <div class="col-md-8">
        <div class="card card-outline card-primary mb-0">
            <div class="card-body py-2">
                <form method="GET" action="{{ route('wa-gateway.index') }}" class="form-inline">
                    <label class="mr-2 mb-0 font-weight-bold"><i class="fas fa-crown text-warning mr-1"></i> Pilih Tenant:</label>
                    <select name="tenant_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                        <option value="">-- Pilih Tenant --</option>
                        @foreach($tenants as $t)
                            <option value="{{ $t->id }}" {{ $selectedTenant?->id == $t->id ? 'selected' : '' }}>
                                {{ $t->name }} ({{ $t->email }})
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

@if(auth()->user()->isSuperAdmin() && ! $isSelfHostedApp && !$selectedTenant)
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="fab fa-whatsapp fa-3x mb-3 text-success"></i>
                <p>Pilih tenant di atas untuk mengatur WA Gateway mereka.</p>
            </div>
        </div>
    </div>
</div>
@else

<div class="row">
    <div class="col-md-8">
        <input type="hidden" id="wa_tenant_id_js" value="{{ auth()->user()->isSuperAdmin() && ! $isSelfHostedApp ? ($selectedTenant?->id ?? '') : '' }}">
        <div class="card">
            <div class="card-body py-2">
                <ul class="nav nav-pills">
                    <li class="nav-item">
                        <a href="{{ route('wa-gateway.index', auth()->user()->isSuperAdmin() && ! $isSelfHostedApp && $selectedTenant ? ['tenant_id' => $selectedTenant->id] : []) }}" class="nav-link {{ $activeTab === 'overview' ? 'active' : '' }}">
                            <i class="fas fa-sliders-h mr-1"></i>Gateway & Template
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('wa-gateway.index', array_filter([
                            'tab' => 'devices',
                            'tenant_id' => auth()->user()->isSuperAdmin() && ! $isSelfHostedApp ? ($selectedTenant?->id ?? null) : null,
                        ])) }}" class="nav-link {{ $activeTab === 'devices' ? 'active' : '' }}">
                            <i class="fas fa-mobile-alt mr-1"></i>Manajemen Device
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('wa-gateway.index', array_filter([
                            'tab' => 'keyword-rules',
                            'tenant_id' => auth()->user()->isSuperAdmin() && ! $isSelfHostedApp ? ($selectedTenant?->id ?? null) : null,
                        ])) }}" class="nav-link {{ $activeTab === 'keyword-rules' ? 'active' : '' }}">
                            <i class="fas fa-robot mr-1"></i>Keyword Rules
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        @if($activeTab === 'overview')
        <div class="card card-outline card-success" id="wa-onboarding-wizard">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-route mr-1"></i> Wizard Onboarding WhatsApp</h3>
            </div>
            <div class="card-body pb-2">
                <p class="text-muted small mb-3">Ikuti 5 langkah ini untuk menyiapkan WhatsApp {{ $isSelfHostedApp ? 'instance self-hosted' : 'tenant' }} dari nol sampai siap kirim.</p>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-secondary mr-2" data-wizard-badge="1">1</span>
                                <strong>Validasi Koneksi Gateway</strong>
                            </div>
                            <p class="text-muted small mb-2">Cek gateway internal dapat diakses sebelum setup device.</p>
                            <button type="button" class="btn btn-sm btn-info" onclick="scrollToWaSection('wa-section-connection')">Buka Koneksi</button>
                            <button type="button" class="btn btn-sm btn-outline-success ml-1" data-wizard-complete="1">Tandai Selesai</button>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-secondary mr-2" data-wizard-badge="2">2</span>
                                <strong>Tambahkan Device</strong>
                            </div>
                            <p class="text-muted small mb-2">Masuk ke tab Device untuk membuat sesi perangkat WA {{ $isSelfHostedApp ? 'utama instance' : 'tenant' }}.</p>
                            <a href="{{ $waDevicesTabUrl }}" class="btn btn-sm btn-primary">Buka Manajemen Device</a>
                            <button type="button" class="btn btn-sm btn-outline-success ml-1" data-wizard-complete="2">Tandai Selesai</button>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-secondary mr-2" data-wizard-badge="3">3</span>
                                <strong>Scan QR Device</strong>
                            </div>
                            <p class="text-muted small mb-2">Di tab Device, klik <strong>Scan QR</strong>, lalu tautkan WhatsApp.</p>
                            <a href="{{ $waDevicesTabUrl }}" class="btn btn-sm btn-primary">Lanjut Scan QR</a>
                            <button type="button" class="btn btn-sm btn-outline-success ml-1" data-wizard-complete="3">Tandai Selesai</button>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-secondary mr-2" data-wizard-badge="4">4</span>
                                <strong>Aktifkan Otomasi & Blast</strong>
                            </div>
                            <p class="text-muted small mb-2">Nyalakan notifikasi otomatis, optimasi blast, dan anti-spam sesuai kebutuhan.</p>
                            <button type="button" class="btn btn-sm btn-info" onclick="scrollToWaSection('wa-section-notification')">Buka Pengaturan</button>
                            <button type="button" class="btn btn-sm btn-outline-success ml-1" data-wizard-complete="4">Tandai Selesai</button>
                        </div>
                    </div>
                    <div class="col-md-12 mb-1">
                        <div class="border rounded p-3">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-secondary mr-2" data-wizard-badge="5">5</span>
                                <strong>Uji Template & Simpan</strong>
                            </div>
                            <p class="text-muted small mb-2">Review template, test kirim ke CS, lalu simpan konfigurasi WhatsApp.</p>
                            <button type="button" class="btn btn-sm btn-info" onclick="scrollToWaSection('wa-section-template')">Buka Template</button>
                            <button type="button" class="btn btn-sm btn-outline-success ml-1" data-wizard-complete="5">Tandai Selesai</button>
                            <button type="button" class="btn btn-sm btn-outline-dark ml-1" id="wa-wizard-reset">Reset Wizard</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fab fa-whatsapp text-success mr-1"></i> Integrasi WhatsApp Gateway
                @if(auth()->user()->isSuperAdmin() && ! $isSelfHostedApp && $selectedTenant)
                    <span class="badge badge-primary ml-2">{{ $selectedTenant->name }}</span>
                @endif
                </h3>
            </div>
            <form action="{{ route('tenant-settings.update-wa') }}" method="POST">
                @csrf
                @method('PUT')
                @if(auth()->user()->isSuperAdmin() && ! $isSelfHostedApp && $selectedTenant)
                <input type="hidden" name="tenant_id" value="{{ $selectedTenant->id }}">
                @endif
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-4 col-xl-3 mb-3">
                            <div class="list-group sticky-top" style="top: 85px;">
                                <a href="#wa-section-connection" class="list-group-item list-group-item-action">1. Koneksi Gateway</a>
                                <a href="#wa-section-notification" class="list-group-item list-group-item-action">2. Notifikasi Otomatis</a>
                                <a href="#wa-section-ticket-group" class="list-group-item list-group-item-action">3. Notifikasi Tiket ke Grup</a>
                                <a href="#wa-section-blast" class="list-group-item list-group-item-action">4. Pengiriman WA Blast</a>
                                <a href="#wa-section-antispam" class="list-group-item list-group-item-action">5. Anti-Spam</a>
                                <a href="#wa-section-template" class="list-group-item list-group-item-action">6. Template Pesan</a>
                            </div>
                        </div>
                        <div class="col-lg-8 col-xl-9">
                            <div class="card card-outline card-info mb-3" id="wa-section-connection">
                                <div class="card-header py-2"><strong>Koneksi Gateway</strong></div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>URL Gateway</label>
                                        <input type="url" name="wa_gateway_url" class="form-control"
                                            value="{{ old('wa_gateway_url', config('wa.multi_session.public_url')) }}"
                                            readonly>
                                        <small class="text-muted">Terkunci ke gateway internal rafen.</small>
                                    </div>
                                    <div class="alert alert-light border mb-3">
                                        <div class="small text-muted mb-1"><strong>Kredensial gateway dikelola internal.</strong></div>
                                        <div class="small text-muted mb-0">Header <code>key</code> (master key) terisi otomatis dari environment server. Header <code>Authorization</code> ditangani internal per device, sehingga tidak ditampilkan di halaman ini.</div>
                                    </div>
                                    <button type="button" class="btn btn-info btn-sm mb-2" id="btn-test-wa" onclick="testWaGateway()">
                                        <i class="fas fa-plug"></i> Test Koneksi
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm mb-2 ml-1" id="btn-test-wa-meta" onclick="testWaMetaCloud()">
                                        <i class="fas fa-cloud"></i> Test Meta Cloud API
                                    </button>
                                    <div id="wa-test-result" class="mb-2"></div>
                                    <div id="wa-meta-test-result" class="mb-2"></div>
                                    <div id="wa-test-detail" class="mt-1" style="display:none;">
                                        <small class="text-muted d-block mb-1">Detail respons gateway:</small>
                                        <pre id="wa-test-detail-body" class="bg-light border rounded p-2" style="font-size:11px;max-height:200px;overflow:auto;white-space:pre-wrap;"></pre>
                                    </div>
                                </div>
                            </div>

                            <div class="card card-outline card-primary mb-3" id="wa-section-notification">
                                <div class="card-header py-2"><strong>Notifikasi Otomatis</strong></div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="wa_notify_registration" name="wa_notify_registration" value="1" {{ $settings->wa_notify_registration ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="wa_notify_registration">Notifikasi Registrasi Pelanggan Baru</label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="wa_notify_invoice" name="wa_notify_invoice" value="1" {{ $settings->wa_notify_invoice ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="wa_notify_invoice">Notifikasi Tagihan Baru</label>
                                        </div>
                                    </div>
                                    <div class="form-group mb-0">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="wa_notify_payment" name="wa_notify_payment" value="1" {{ $settings->wa_notify_payment ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="wa_notify_payment">Notifikasi Konfirmasi Pembayaran</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Notifikasi Tiket ke Grup WA --}}
                            <div class="card card-outline card-teal mb-3" id="wa-section-ticket-group">
                                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                                    <strong>Notifikasi Tiket ke Grup WA</strong>
                                    <span class="badge badge-info">Baru</span>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">Setiap kali tiket baru dibuat, notifikasi akan dikirim ke grup WhatsApp yang dipilih. Pastikan nomor WA yang terhubung sudah bergabung ke grup tersebut.</p>

                                    <div class="form-group">
                                        <label>Grup WA Tujuan Notifikasi Tiket</label>
                                        <div class="input-group">
                                            <input type="text" id="wa_ticket_group_display"
                                                class="form-control"
                                                placeholder="Pilih grup dari daftar..."
                                                value="{{ $settings->wa_ticket_group_name ?? '' }}"
                                                readonly>
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-info" id="btn-load-groups">
                                                    <i class="fas fa-sync-alt mr-1"></i>Muat Daftar Grup
                                                </button>
                                                @if($settings->wa_ticket_group_id)
                                                <button type="button" class="btn btn-outline-danger" id="btn-clear-ticket-group">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                @endif
                                            </div>
                                        </div>
                                        @if($settings->wa_ticket_group_id)
                                        <small class="text-muted">ID Grup: <code>{{ $settings->wa_ticket_group_id }}</code></small>
                                        @endif
                                    </div>

                                    <div id="wa-groups-list" class="d-none">
                                        <label>Pilih Grup:</label>
                                        <div id="wa-groups-container" class="border rounded p-2" style="max-height:250px;overflow-y:auto;background:#f8f9fa;">
                                            <p class="text-muted small mb-0">Klik "Muat Daftar Grup" untuk memuat daftar grup.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card card-outline card-success mb-3" id="wa-section-blast">
                                <div class="card-header py-2"><strong>Pengiriman WA Blast</strong></div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="wa_broadcast_enabled" name="wa_broadcast_enabled" value="1" {{ $settings->wa_broadcast_enabled ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="wa_broadcast_enabled">Aktifkan Fitur WA Blast</label>
                                        </div>
                                    </div>
                                    <div class="form-group mb-2">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="wa_blast_multi_device" name="wa_blast_multi_device" value="1" {{ ($settings->wa_blast_multi_device ?? true) ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="wa_blast_multi_device">Distribusi ke Multi Device Aktif (Round Robin + Failover)</label>
                                        </div>
                                    </div>
                                    <div class="form-group mb-2">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="wa_blast_message_variation" name="wa_blast_message_variation" value="1" {{ ($settings->wa_blast_message_variation ?? true) ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="wa_blast_message_variation">Variasi Pesan Natural Profesional</label>
                                        </div>
                                        <small class="text-muted">Menambahkan pembuka/penutup profesional yang bervariasi saat WA Blast agar tidak terlalu seragam.</small>
                                    </div>
                                    <div class="form-row mb-0">
                                        <div class="form-group col-md-6 mb-0">
                                            <label class="mb-1">Delay Blast Minimal (detik)</label>
                                            <input type="number" name="wa_blast_delay_min_ms" class="form-control"
                                                value="{{ old('wa_blast_delay_min_ms', number_format((($settings->wa_blast_delay_min_ms ?? 2000) / 1000), 1, '.', '')) }}"
                                                min="2" max="15" step="0.1">
                                        </div>
                                        <div class="form-group col-md-6 mb-0">
                                            <label class="mb-1">Delay Blast Maksimal (detik)</label>
                                            <input type="number" name="wa_blast_delay_max_ms" class="form-control"
                                                value="{{ old('wa_blast_delay_max_ms', number_format((($settings->wa_blast_delay_max_ms ?? 3200) / 1000), 1, '.', '')) }}"
                                                min="2" max="20" step="0.1">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card card-outline card-warning mb-3" id="wa-section-antispam">
                                <div class="card-header py-2"><strong>Anti-Spam</strong></div>
                                <div class="card-body">
                                    <p class="text-muted small">Mencegah akun WA diblokir saat melakukan pengiriman pesan massal.</p>
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="wa_antispam_enabled" name="wa_antispam_enabled" value="1" {{ ($settings->wa_antispam_enabled ?? true) ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="wa_antispam_enabled">Aktifkan Anti-Spam</label>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>Delay Antar Pesan (detik)</label>
                                            <input type="number" name="wa_antispam_delay_ms" class="form-control"
                                                value="{{ old('wa_antispam_delay_ms', number_format((($settings->wa_antispam_delay_ms ?? 2000) / 1000), 1, '.', '')) }}"
                                                min="0.5" max="10" step="0.1">
                                            <small class="text-muted">Rekomendasi: 2 detik.</small>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label>Maks Pesan per Menit</label>
                                            <input type="number" name="wa_antispam_max_per_minute" class="form-control"
                                                value="{{ old('wa_antispam_max_per_minute', $settings->wa_antispam_max_per_minute ?? 10) }}"
                                                min="1" max="20">
                                            <small class="text-muted">Rekomendasi: 10.</small>
                                        </div>
                                    </div>
                                    <div class="form-group mb-0">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="wa_msg_randomize" name="wa_msg_randomize" value="1" {{ ($settings->wa_msg_randomize ?? true) ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="wa_msg_randomize">Randomisasi Ref Pesan</label>
                                        </div>
                                        <small class="text-muted">Menambahkan karakter acak tak terlihat di akhir pesan agar konten tidak identik antar penerima.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="card card-outline card-secondary mb-0" id="wa-section-template">
                                <div class="card-header py-2"><strong>Template Pesan</strong></div>
                                <div class="card-body">
                                    <p class="text-muted small mb-1">Placeholder yang tersedia:</p>
                                    <div class="mb-3" style="font-size:12px;line-height:2;">
                                        <code>{name}</code> Nama pelanggan &nbsp;
                                        <code>{customer_id}</code> ID pelanggan &nbsp;
                                        <code>{profile}</code> Nama paket &nbsp;
                                        <code>{service}</code> Tipe (PPPoE/Hotspot) &nbsp;
                                        <code>{total}</code> Harga/Tagihan &nbsp;
                                        <code>{due_date}</code> Jatuh tempo &nbsp;
                                        <code>{invoice_no}</code> No. invoice &nbsp;
                                        <code>{paid_at}</code> Waktu bayar &nbsp;
                                        <code>{cs_number}</code> Nomor CS (dari Pengaturan) &nbsp;
                                        <code>{bank_account}</code> Info rekening bank &nbsp;
                                        <code>{payment_link}</code> Link bayar pelanggan &nbsp;
                                        <code>{username}</code> Username PPP &nbsp;
                                        <code>{portal_url}</code> <span class="text-success">URL portal pelanggan</span> &nbsp;
                                        <code>{password_clientarea}</code> <span class="text-success">Password portal pelanggan (hanya template Registrasi)</span>
                                    </div>
                                    <p class="text-muted small mb-2">Rotasi template aktif otomatis. Jika ingin custom beberapa versi pesan, pisahkan setiap versi dengan baris <code>---</code> dalam kolom template yang sama.</p>
                                    <p class="text-muted small mb-3">Tombol <strong>Test Kirim</strong> mengirim pesan ke nomor HP bisnis (CS) dengan data dummy. Simpan dulu sebelum test agar template terbaru digunakan.</p>

                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="mb-0">Template Registrasi</label>
                                            <button type="button" class="btn btn-outline-success btn-sm btn-test-template" data-type="registration">
                                                <i class="fas fa-paper-plane mr-1"></i>Test Kirim ke CS
                                            </button>
                                        </div>
                                        <textarea name="wa_template_registration" class="form-control" rows="6"
                                            placeholder="{{ $settings->getDefaultTemplate('registration') }}">{{ old('wa_template_registration', $settings->wa_template_registration) }}</textarea>
                                        <small class="text-muted">Kosongkan untuk template default humanis + rotasi otomatis.</small>
                                        <div class="test-template-result mt-1" data-for="registration"></div>
                                    </div>
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="mb-0">Template Tagihan (Invoice)</label>
                                            <button type="button" class="btn btn-outline-success btn-sm btn-test-template" data-type="invoice">
                                                <i class="fas fa-paper-plane mr-1"></i>Test Kirim ke CS
                                            </button>
                                        </div>
                                        <textarea name="wa_template_invoice" class="form-control" rows="6"
                                            placeholder="{{ $settings->getDefaultTemplate('invoice') }}">{{ old('wa_template_invoice', $settings->wa_template_invoice) }}</textarea>
                                        <small class="text-muted">Kosongkan untuk template default humanis + rotasi otomatis.</small>
                                        <div class="test-template-result mt-1" data-for="invoice"></div>
                                    </div>
                                    <div class="form-group mb-0">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="mb-0">Template Pembayaran</label>
                                            <button type="button" class="btn btn-outline-success btn-sm btn-test-template" data-type="payment">
                                                <i class="fas fa-paper-plane mr-1"></i>Test Kirim ke CS
                                            </button>
                                        </div>
                                        <textarea name="wa_template_payment" class="form-control" rows="6"
                                            placeholder="{{ $settings->getDefaultTemplate('payment') }}">{{ old('wa_template_payment', $settings->wa_template_payment) }}</textarea>
                                        <small class="text-muted">Kosongkan untuk template default humanis + rotasi otomatis.</small>
                                        <div class="test-template-result mt-1" data-for="payment"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Simpan Pengaturan WhatsApp
                    </button>
                </div>
            </form>
        </div>
        @endif

        @if($activeTab === 'devices')
        <div class="card" id="wa-device-management-card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-mobile-alt mr-1"></i> Manajemen Device WA</h3>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Tambahkan beberapa device untuk operasional CS/kasir, lalu tentukan device default untuk pengiriman otomatis.</p>

                <form id="wa-device-form">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="wa_device_name">Nama Device</label>
                            <input type="text" id="wa_device_name" name="device_name" class="form-control" placeholder="Contoh: CS Utama" maxlength="120" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="wa_number">Nomor WA Device</label>
                            <input type="text" id="wa_number" name="wa_number" class="form-control" placeholder="628xxx" maxlength="30">
                            <small class="text-muted">Nomor HP yang terdaftar di WA (untuk matching pesan masuk).</small>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="wa_session_id">Session ID (opsional)</label>
                            <input type="text" id="wa_session_id" name="session_id" class="form-control" placeholder="Otomatis jika dikosongkan" maxlength="150">
                            <small class="text-muted">Karakter: huruf, angka, titik, underscore, dash.</small>
                        </div>
                        <div class="form-group col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-success btn-block" id="wa-device-add-button">
                                <i class="fas fa-plus mr-1"></i>Tambah
                            </button>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <div class="custom-control custom-switch mt-2">
                                <input type="checkbox" class="custom-control-input" id="wa_is_warmup" name="is_warmup" value="1">
                                <label class="custom-control-label" for="wa_is_warmup">Mode Warmup</label>
                            </div>
                        </div>
                        <div class="form-group col-md-5">
                            <label for="wa_warmup_until">Warmup Sampai (opsional)</label>
                            <input type="datetime-local" id="wa_warmup_until" name="warmup_until" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="wa_warmup_max_per_batch">Maks Pesan per Batch</label>
                            <input type="number" id="wa_warmup_max_per_batch" name="warmup_max_per_batch" class="form-control" min="0" max="100" value="0">
                            <small class="text-muted">Set `0` agar sistem atur kenaikan limit warmup bertahap.</small>
                        </div>
                    </div>
                </form>

                <div id="wa-device-result" class="mb-3"></div>

                <form id="wa-sticky-reset-form" class="border rounded p-2 mb-3 bg-light">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-8 mb-2">
                            <label for="wa_sticky_phone" class="mb-1">Pindahkan Pengirim Chat Pelanggan</label>
                            <input type="text" id="wa_sticky_phone" name="phone" class="form-control form-control-sm" placeholder="Contoh: 081234567890 atau 6281234567890" maxlength="30">
                            <small class="text-muted">Isi nomor pelanggan yang ingin dipindah pengirimnya. Setelah reset, pesan berikutnya ke nomor ini akan dipilih ulang oleh sistem (bisa pindah ke device lain).</small>
                        </div>
                        <div class="form-group col-md-4 mb-2">
                            <button type="submit" class="btn btn-outline-dark btn-sm btn-block" id="wa-sticky-reset-button">
                                <i class="fas fa-sync-alt mr-1"></i>Reset Mapping Nomor
                            </button>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Device</th>
                                <th>Session ID</th>
                                <th>Nomor WA</th>
                                <th>Default</th>
                                <th>Koneksi</th>
                                <th>Warmup</th>
                                <th class="text-right" style="min-width: 210px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="wa-device-table-body">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">Memuat data device...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Platform Device Card (hanya untuk tenant, bukan super admin) --}}
        @if(!auth()->user()->isSuperAdmin() && ! $isSelfHostedApp)
        <div class="card card-outline card-info mt-2">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-shield-alt mr-1 text-info"></i> Gunakan Platform Device</h5>
            </div>
            <div class="card-body">
                @if($settings && $settings->hasWaPlatformDevice())
                    <div class="alert alert-success mb-2">
                        <i class="fas fa-check-circle mr-1"></i>
                        Tenant Anda menggunakan <strong>Platform Device</strong> milik penyedia layanan untuk pengiriman notifikasi WA.
                        @if($settings->waPlatformDevice)
                            <div class="mt-1 small">Device: <strong>{{ $settings->waPlatformDevice->device_name }}</strong>
                            ({{ $settings->waPlatformDevice->wa_number }})</div>
                        @endif
                    </div>
                    <p class="text-muted small mb-0">Hubungi Super Admin jika ingin berhenti menggunakan platform device.</p>
                @elseif(isset($pendingPlatformRequest) && $pendingPlatformRequest)
                    <div class="alert alert-warning mb-2">
                        <i class="fas fa-clock mr-1"></i>
                        Permintaan Anda sedang diproses oleh Super Admin. Harap tunggu.
                    </div>
                    <button class="btn btn-sm btn-outline-danger" id="btn-cancel-platform-request">
                        <i class="fas fa-times mr-1"></i>Batalkan Permintaan
                    </button>
                @else
                    <p class="text-muted small mb-3">
                        Tidak ingin repot menghubungkan perangkat WA sendiri? Gunakan device WA platform milik penyedia layanan.
                        Kirim permintaan dan Super Admin akan menyetujuinya.
                    </p>
                    <div class="form-group mb-2">
                        <label class="small">Alasan (opsional)</label>
                        <textarea id="platform-device-reason" class="form-control form-control-sm" rows="2" maxlength="500"
                                  placeholder="Contoh: Tidak punya nomor WA khusus untuk bisnis..."></textarea>
                    </div>
                    <button class="btn btn-primary btn-sm" id="btn-request-platform-device">
                        <i class="fas fa-paper-plane mr-1"></i>Request Pakai Device Platform
                    </button>
                @endif
            </div>
        </div>
        @endif
        @endif

        @if($activeTab === 'keyword-rules')
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-robot mr-1"></i> Keyword Rules Bot</h3>
                <div class="card-tools">
                    <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#modal-keyword-add">
                        <i class="fas fa-plus mr-1"></i> Tambah Rule
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="p-3 border-bottom bg-light">
                    <small class="text-muted"><i class="fas fa-info-circle mr-1"></i>
                        Keyword rules akan diproses <strong>sebelum</strong> intent bawaan bot. Gunakan kata kunci yang spesifik agar tidak bentrok dengan intent lain.
                    </small>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" id="keyword-rules-table">
                        <thead>
                            <tr>
                                <th style="width:40px">P</th>
                                <th>Keywords</th>
                                <th>Balasan</th>
                                <th style="width:80px">Status</th>
                                <th style="width:100px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="keyword-rules-tbody">
                            <tr><td colspan="5" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin mr-1"></i>Memuat...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- Modal Tambah Keyword Rule --}}
        <div class="modal fade" id="modal-keyword-add" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal-keyword-title"><i class="fas fa-robot mr-1"></i> Tambah Keyword Rule</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="keyword-rule-id" value="">
                        <div class="form-group">
                            <label>Keywords <small class="text-muted">(satu per baris, bot akan cocokkan salah satu)</small></label>
                            <textarea id="keyword-rule-keywords" class="form-control" rows="4" placeholder="mati lampu&#10;gangguan listrik&#10;tidak ada sinyal"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Balasan Bot</label>
                            <textarea id="keyword-rule-reply" class="form-control" rows="5" placeholder="Maaf, kami sedang mengalami gangguan. Tim teknis sedang bekerja..."></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Prioritas <small class="text-muted">(0 = tertinggi)</small></label>
                                <input type="number" id="keyword-rule-priority" class="form-control" value="0" min="0" max="255">
                            </div>
                            <div class="form-group col-md-6 d-flex align-items-end">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="keyword-rule-active" checked>
                                    <label class="custom-control-label" for="keyword-rule-active">Aktif</label>
                                </div>
                            </div>
                        </div>
                        <div id="keyword-rule-error" class="alert alert-danger d-none"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-success btn-sm" onclick="saveKeywordRule()">
                            <i class="fas fa-save mr-1"></i> Simpan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="wa-qr-modal" tabindex="-1" role="dialog" aria-labelledby="wa-qr-modal-label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="wa-qr-modal-label"><i class="fas fa-qrcode mr-1"></i> Scan QR WhatsApp</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="small text-muted mb-2">Device: <strong id="wa-qr-device-name">-</strong></div>
                        <div id="wa-qr-alert" class="mb-2"></div>
                        <div id="wa-qr-canvas-wrap" class="text-center d-none">
                            <div id="wa-qr-canvas" class="d-inline-block p-2 bg-white border rounded"></div>
                        </div>
                        <div class="small text-primary mt-2 mb-0" id="wa-qr-countdown"></div>
                        <div class="small text-muted mt-2 mb-0" id="wa-qr-meta"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="refreshDeviceQrStatus('status')">Cek Status</button>
                        <button type="button" class="btn btn-warning btn-sm" onclick="refreshDeviceQrStatus('restart')">Generate QR Baru</button>
                        <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="wa-warmup-modal" tabindex="-1" role="dialog" aria-labelledby="wa-warmup-modal-label" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="wa-warmup-modal-label"><i class="fas fa-fire mr-1"></i> Atur Warmup Device</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="wa_warmup_device_id" value="">
                        <div class="small text-muted mb-2">Device: <strong id="wa_warmup_device_name">-</strong></div>
                        <div class="custom-control custom-switch mb-3">
                            <input type="checkbox" class="custom-control-input" id="wa_warmup_modal_enabled">
                            <label class="custom-control-label" for="wa_warmup_modal_enabled">Aktifkan mode warmup</label>
                        </div>
                        <div class="form-group">
                            <label for="wa_warmup_modal_until">Warmup Sampai (opsional)</label>
                            <input type="datetime-local" id="wa_warmup_modal_until" class="form-control">
                            <small class="text-muted">Kosongkan untuk default otomatis (14 hari sejak warmup dimulai).</small>
                        </div>
                        <div class="form-group mb-0">
                            <label for="wa_warmup_modal_max">Maks Pesan per Batch</label>
                            <input type="number" id="wa_warmup_modal_max" class="form-control" min="0" max="100" value="0">
                            <small class="text-muted">Set `0` agar sistem mengatur warm-up kapasitas per tahap.</small>
                        </div>
                        <div class="mb-2">
                            <div class="small text-muted mb-1">Preset Cepat</div>
                            <div class="btn-group btn-group-sm d-flex" role="group">
                                <button type="button" class="btn btn-outline-secondary w-100" onclick="applyWarmupPreset('baru')">Baru</button>
                                <button type="button" class="btn btn-outline-secondary w-100" onclick="applyWarmupPreset('menengah')">Menengah</button>
                                <button type="button" class="btn btn-outline-secondary w-100" onclick="applyWarmupPreset('siap')">Siap Produksi</button>
                            </div>
                        </div>
                        <div id="wa-warmup-estimate" class="alert alert-light border py-2 px-3 mb-2 small text-muted"></div>
                        <div class="border rounded p-2 bg-light">
                            <div class="small font-weight-bold mb-1">Riwayat Warmup (Terakhir)</div>
                            <div id="wa-warmup-history" class="small text-muted">Belum ada histori.</div>
                        </div>
                        <div id="wa-warmup-modal-alert" class="mt-2"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Tutup</button>
                        <button type="button" class="btn btn-primary btn-sm" id="wa-warmup-modal-save-btn">
                            <i class="fas fa-save mr-1"></i>Simpan Warmup
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Sidebar info --}}
    <div class="col-md-4">
        @if(auth()->user()->isSuperAdmin() && ! $isSelfHostedApp)
        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-server mr-1"></i> {{ $isSelfHostedApp ? 'Service WA Gateway' : 'Service wa-multi-session' }}</h3>
            </div>
            <div class="card-body">
                <div id="wa-service-status" class="small text-muted mb-2">
                    @if(!empty($waServiceStatus['data']))
                        @php
                            $initialWaServiceRunning = (bool) ($waServiceStatus['data']['running'] ?? false);
                            $initialWaServiceLabel = $initialWaServiceRunning
                                ? 'RUNNING'
                                : strtoupper((string) ($waServiceStatus['data']['pm2_status'] ?? 'STOPPED'));
                        @endphp
                        Status: <strong>{{ $initialWaServiceLabel }}</strong>
                        @if(!empty($waServiceStatus['data']['pm2_pid']))
                            | PID: {{ $waServiceStatus['data']['pm2_pid'] }}
                        @endif
                        @if(!empty($waServiceStatus['data']['url']))
                            | URL: {{ $waServiceStatus['data']['url'] }}
                        @endif
                    @else
                        Belum dicek.
                    @endif
                </div>
                <div class="btn-group btn-group-sm d-flex mb-2" role="group">
                    <button type="button" class="btn btn-outline-warning w-100" onclick="restartWaService()">Restart PM2</button>
                </div>
                <div id="wa-service-result"></div>
            </div>
        </div>
        @endif

        <div class="card card-outline card-success">
            <div class="card-header">
                <h3 class="card-title"><i class="fab fa-whatsapp mr-1"></i> Status Gateway</h3>
            </div>
            <div class="card-body">
                @if($settings->hasWaConfigured())
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge badge-success mr-2">Terkonfigurasi</span>
                        <small class="text-muted text-truncate">{{ config('wa.multi_session.public_url') }}</small>
                    </div>
                    @if($canAccessWaBlast)
                    <a href="{{ route('wa-blast.index') }}" class="btn btn-outline-success btn-sm btn-block">
                        <i class="fas fa-paper-plane mr-1"></i> Buka WA Blast
                    </a>
                    @endif
                @else
                    <div class="text-center text-muted py-2">
                        <i class="fab fa-whatsapp fa-2x mb-2 d-block"></i>
                        <small>Gateway internal belum siap.<br>Periksa variabel WA_MULTI_SESSION_*.</small>
                    </div>
                @endif

                @if($settings->hasWaConfigured())
                    <hr>
                    <div class="small text-muted mb-2">
                        Session ID {{ $isSelfHostedApp ? 'Sistem' : 'Tenant' }}: <code>{{ 'tenant-' . ($settings->user_id ?? auth()->user()->effectiveOwnerId()) }}</code>
                    </div>
                    <div class="small text-muted mb-0">Session dikelola otomatis oleh gateway lokal.</div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pintasan</h3>
            </div>
            <div class="card-body p-0">
                <a href="{{ route('wa-gateway.index', array_filter([
                    'tab' => 'devices',
                    'tenant_id' => auth()->user()->isSuperAdmin() && ! $isSelfHostedApp ? ($selectedTenant?->id ?? null) : null,
                ])) }}" class="d-flex align-items-center p-3 border-bottom text-dark text-decoration-none">
                    <i class="fas fa-mobile-alt fa-fw mr-2 text-primary"></i>
                    <span>Manajemen Device</span>
                    <i class="fas fa-chevron-right ml-auto text-muted small"></i>
                </a>
                @if($canAccessWaBlast)
                <a href="{{ route('wa-blast.index') }}" class="d-flex align-items-center p-3 border-bottom text-dark text-decoration-none">
                    <i class="fas fa-paper-plane fa-fw mr-2 text-success"></i>
                    <span>WA Blast</span>
                    <i class="fas fa-chevron-right ml-auto text-muted small"></i>
                </a>
                @endif
                <a href="{{ route('tenant-settings.index') }}" class="d-flex align-items-center p-3 text-dark text-decoration-none">
                    <i class="fas fa-cog fa-fw mr-2 text-secondary"></i>
                    <span>{{ $isSelfHostedApp ? 'Pengaturan Sistem' : 'Pengaturan Tenant' }}</span>
                    <i class="fas fa-chevron-right ml-auto text-muted small"></i>
                </a>
            </div>
        </div>
    </div>
</div>
@endif {{-- end super admin tenant check --}}

@if(!auth()->user()->isSuperAdmin() && ! $isSelfHostedApp)
<script>
(function () {
    var btnRequest = document.getElementById('btn-request-platform-device');
    var btnCancel  = document.getElementById('btn-cancel-platform-request');

    if (btnRequest) {
        btnRequest.addEventListener('click', function () {
            var reason  = (document.getElementById('platform-device-reason') || {}).value || '';
            var csrfEl  = document.querySelector('meta[name="csrf-token"]');
            var csrf    = csrfEl ? csrfEl.content : '';
            btnRequest.disabled = true;
            fetch('{{ route("wa-platform-device.request") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ reason: reason }),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    window.AppAjax.showToast(data.message, 'success');
                    setTimeout(function () { location.reload(); }, 1200);
                } else {
                    window.AppAjax.showToast(data.message || 'Gagal mengirim permintaan.', 'danger');
                    btnRequest.disabled = false;
                }
            })
            .catch(function () {
                window.AppAjax.showToast('Terjadi kesalahan. Silakan coba lagi.', 'danger');
                btnRequest.disabled = false;
            });
        });
    }

    if (btnCancel) {
        btnCancel.addEventListener('click', function () {
            if (!confirm('Batalkan permintaan platform device?')) return;
            var csrfEl = document.querySelector('meta[name="csrf-token"]');
            var csrf   = csrfEl ? csrfEl.content : '';
            btnCancel.disabled = true;
            fetch('{{ route("wa-platform-device.cancel") }}', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    window.AppAjax.showToast(data.message, 'success');
                    setTimeout(function () { location.reload(); }, 1200);
                } else {
                    window.AppAjax.showToast(data.message || 'Gagal membatalkan permintaan.', 'danger');
                    btnCancel.disabled = false;
                }
            })
            .catch(function () {
                window.AppAjax.showToast('Terjadi kesalahan. Silakan coba lagi.', 'danger');
                btnCancel.disabled = false;
            });
        });
    }
})();
</script>
@endif
@endsection

@push('scripts')
<script src="{{ asset('vendor/qrcodejs/qrcode.min.js') }}"></script>
<script>
function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function fetchJson(url, options) {
    var config = Object.assign({ method: 'GET' }, options || {});
    config.headers = Object.assign({
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    }, config.headers || {});

    return fetch(url, config).then(function (response) {
        var contentType = response.headers.get('content-type') || '';

        if (contentType.includes('application/json')) {
            return response.json().then(function (data) {
                return { response: response, data: data };
            });
        }

        return response.text().then(function () {
            var message = 'Server mengembalikan respons non-JSON (HTTP ' + response.status + ').';

            if (response.status === 419) {
                message = 'Sesi habis (CSRF). Silakan refresh halaman ini.';
            } else if (response.status === 401) {
                message = 'Sesi login tidak valid. Silakan login ulang.';
            } else if (response.status === 403) {
                message = 'Akses ditolak untuk aksi ini.';
            }

            throw new Error(message);
        });
    });
}

var waWizardStorageKey = 'wa-onboarding-{{ (int) ($settings->user_id ?? auth()->user()->effectiveOwnerId()) }}';

function scrollToWaSection(sectionId) {
    var section = document.getElementById(sectionId);
    if (!section) {
        return;
    }

    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function readWaWizardState() {
    try {
        var raw = localStorage.getItem(waWizardStorageKey);
        if (!raw) {
            return {};
        }

        var parsed = JSON.parse(raw);
        return typeof parsed === 'object' && parsed !== null ? parsed : {};
    } catch (error) {
        return {};
    }
}

function writeWaWizardState(state) {
    try {
        localStorage.setItem(waWizardStorageKey, JSON.stringify(state));
    } catch (error) {
        // no-op
    }
}

function renderWaWizard() {
    var state = readWaWizardState();
    document.querySelectorAll('[data-wizard-badge]').forEach(function (badge) {
        var step = String(badge.getAttribute('data-wizard-badge') || '');
        var completed = !!state[step];
        badge.className = 'badge mr-2 ' + (completed ? 'badge-success' : 'badge-secondary');
        badge.textContent = completed ? '✓' : step;
    });
}

function setupWaWizard() {
    var wizard = document.getElementById('wa-onboarding-wizard');
    if (!wizard) {
        return;
    }

    document.querySelectorAll('[data-wizard-complete]').forEach(function (button) {
        button.addEventListener('click', function () {
            var step = String(button.getAttribute('data-wizard-complete') || '');
            var state = readWaWizardState();
            state[step] = true;
            writeWaWizardState(state);
            renderWaWizard();
        });
    });

    var resetButton = document.getElementById('wa-wizard-reset');
    if (resetButton) {
        resetButton.addEventListener('click', function () {
            writeWaWizardState({});
            renderWaWizard();
        });
    }

    renderWaWizard();
}

document.querySelectorAll('.btn-test-template').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var type      = btn.dataset.type;
        var resultDiv = document.querySelector('.test-template-result[data-for="' + type + '"]');
        btn.disabled  = true;
        resultDiv.innerHTML = '<div class="alert alert-info alert-sm py-1 px-2 mb-0"><i class="fas fa-spinner fa-spin mr-1"></i> Mengirim...</div>';

        fetchJson('{{ route("tenant-settings.test-template") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify(Object.assign(getTenantPayload(), { type: type }))
        })
        .then(function (result) {
            var data = result.data;
            btn.disabled = false;
            if (data.success) {
                resultDiv.innerHTML = '<div class="alert alert-success alert-sm py-1 px-2 mb-0"><i class="fas fa-check-circle mr-1"></i>' + escapeHtml(data.message) + '</div>';
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger alert-sm py-1 px-2 mb-0"><i class="fas fa-times-circle mr-1"></i>' + escapeHtml(data.message) + '</div>';
            }
            setTimeout(function () { resultDiv.innerHTML = ''; }, 6000);
        })
        .catch(function (e) {
            btn.disabled = false;
            resultDiv.innerHTML = '<div class="alert alert-danger alert-sm py-1 px-2 mb-0">' + escapeHtml(e.message) + '</div>';
        });
    });
});

function testWaGateway() {
    var resultDiv  = document.getElementById('wa-test-result');
    var detailDiv  = document.getElementById('wa-test-detail');
    var detailBody = document.getElementById('wa-test-detail-body');
    var btn        = document.getElementById('btn-test-wa');

    resultDiv.innerHTML     = '<div class="alert alert-info mb-0"><i class="fas fa-spinner fa-spin mr-1"></i> Menguji koneksi, harap tunggu...</div>';
    detailDiv.style.display = 'none';
    detailBody.textContent  = '';
    btn.disabled            = true;

    var startTime = Date.now();

    fetchJson('{{ route("tenant-settings.test-wa") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(getTenantPayload())
    })
    .then(function (result) {
        var data = result.data;
        var elapsed = ((Date.now() - startTime) / 1000).toFixed(2);
        btn.disabled = false;

        if (data.success) {
            resultDiv.innerHTML =
                '<div class="alert alert-success mb-0">' +
                    '<strong><i class="fas fa-check-circle mr-1"></i> Koneksi Berhasil!</strong><br>' +
                    '<span class="small">' + escapeHtml(data.message) + '</span><br>' +
                    '<span class="small" style="opacity:.8">Waktu: ' + elapsed + 's</span>' +
                '</div>';
        } else {
            var hint = '';
            if (data.http_status === 401) {
                hint = '<li>Token perangkat tidak dikenali gateway.</li>' +
                       '<li>Pastikan token berasal dari device yang aktif di dashboard gateway.</li>' +
                       '<li>Jika perlu format <code>Bearer &lt;token&gt;</code>, tambahkan <code>Bearer </code> di depan nilai Token.</li>';
            } else if (data.http_status === 403) {
                hint = '<li>Gateway menolak akses. Pastikan token memiliki izin yang cukup.</li>';
            } else if (data.network_error) {
                hint = '<li>Pastikan wa-multi-session aktif di PM2 dan route /wa-multi-session bisa diakses.</li>';
            } else {
                hint = '<li>Periksa konfigurasi WA_MULTI_SESSION_* pada environment server.</li>';
            }
            resultDiv.innerHTML =
                '<div class="alert alert-danger mb-0">' +
                    '<strong><i class="fas fa-times-circle mr-1"></i> Koneksi Gagal</strong><br>' +
                    '<span class="small">' + escapeHtml(data.message) + '</span>' +
                    '<ul class="small mt-2 mb-1 pl-3">' + hint + '</ul>' +
                    '<span class="small" style="opacity:.8">Waktu: ' + elapsed + 's</span>' +
                '</div>';
        }
        if (data.gateway_response) {
            detailBody.textContent = typeof data.gateway_response === 'string'
                ? data.gateway_response
                : JSON.stringify(data.gateway_response, null, 2);
            detailDiv.style.display = 'block';
        }
    })
    .catch(error => {
        btn.disabled = false;
        resultDiv.innerHTML = '<div class="alert alert-danger mb-0"><strong><i class="fas fa-times-circle mr-1"></i> Permintaan Gagal</strong><br><span class="small">' + escapeHtml(error.message) + '</span></div>';
    });
}

function testWaMetaCloud() {
    var resultDiv = document.getElementById('wa-meta-test-result');
    var btn = document.getElementById('btn-test-wa-meta');

    resultDiv.innerHTML = '<div class="alert alert-info mb-0"><i class="fas fa-spinner fa-spin mr-1"></i> Mengirim test ke Meta Cloud API...</div>';
    btn.disabled = true;

    fetchJson('{{ route("tenant-settings.test-wa-meta") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(getTenantPayload())
    })
    .then(function (result) {
        var data = result.data;
        btn.disabled = false;
        if (data.success) {
            resultDiv.innerHTML =
                '<div class="alert alert-success mb-0">' +
                    '<strong><i class="fas fa-check-circle mr-1"></i> Test Meta Berhasil</strong><br>' +
                    '<span class="small">' + escapeHtml(data.message || '') + '</span><br>' +
                    '<span class="small text-muted">Tujuan: ' + escapeHtml(data.recipient || '-') + '</span>' +
                '</div>';
            return;
        }

        resultDiv.innerHTML =
            '<div class="alert alert-danger mb-0">' +
                '<strong><i class="fas fa-times-circle mr-1"></i> Test Meta Gagal</strong><br>' +
                '<span class="small">' + escapeHtml(data.message || 'Unknown error') + '</span>' +
            '</div>';
    })
    .catch(function (error) {
        btn.disabled = false;
        resultDiv.innerHTML = '<div class="alert alert-danger mb-0"><strong><i class="fas fa-times-circle mr-1"></i> Permintaan Gagal</strong><br><span class="small">' + escapeHtml(error.message) + '</span></div>';
    });
}

function getTenantPayload() {
    var tenantId = document.getElementById('wa_tenant_id_js')?.value || '';
    if (!tenantId) {
        return {};
    }

    return { tenant_id: Number(tenantId) };
}

function renderWaDeviceBadge(isDefault) {
    if (isDefault) {
        return '<span class="badge badge-success">Default</span>';
    }

    return '<span class="badge badge-secondary">Cadangan</span>';
}

var waDeviceState = {
    devices: [],
    statusTimer: null,
};

function renderWaDeviceTable(devices) {
    var body = document.getElementById('wa-device-table-body');
    if (!body) {
        return;
    }

    if (!Array.isArray(devices) || devices.length === 0) {
        body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Belum ada device. Tambahkan device pertama Anda.</td></tr>';

        return;
    }

    body.innerHTML = devices.map(function (device) {
        var deviceName = escapeHtml(device.device_name || '-');
        var sessionId = escapeHtml(device.session_id || '-');
        var waNumber = device.wa_number ? escapeHtml(device.wa_number) : '<span class="text-muted">-</span>';
        var statusBadge = renderWaDeviceBadge(!!device.is_default);
        var meta = device && typeof device.meta === 'object' && device.meta !== null ? device.meta : {};
        var isWarmup = !!meta.is_warmup;
        var warmupUntil = meta.warmup_until ? '<div class="text-muted small">s/d ' + escapeHtml(String(meta.warmup_until)) + '</div>' : '';
        var warmupLimit = Number(meta.warmup_max_per_batch || 1);
        var isAutoWarmup = !!meta.warmup_auto;
        var warmupMode = isAutoWarmup ? '<div class="text-muted small">Mode: Auto</div>' : '';
        var warmupBadge = isWarmup
            ? ('<span class="badge badge-warning">Warmup</span>' + warmupUntil + warmupMode + '<div class="text-muted small">Max/batch: ' + warmupLimit + '</div>')
            : '<span class="badge badge-light">Normal</span>';
        var operationalButtons = [];
        var configButtons = [];

        if (!device.is_default) {
            configButtons.push('<button type="button" class="btn btn-outline-primary btn-sm text-left text-nowrap w-100" onclick="setDefaultWaDevice(' + Number(device.id) + ')"><i class="fas fa-star mr-1"></i>Jadikan Default</button>');
        }

        operationalButtons.push('<button type="button" class="btn btn-outline-success btn-sm text-left text-nowrap w-100" onclick=\'openQrModal(' + Number(device.id) + ', ' + JSON.stringify(String(device.device_name || 'Device')) + ')\'><i class="fas fa-qrcode mr-1"></i>Scan QR</button>');
        operationalButtons.push('<button type="button" class="btn btn-outline-info btn-sm text-left text-nowrap w-100" onclick="controlDeviceSession(' + Number(device.id) + ', \'status\')"><i class="fas fa-signal mr-1"></i>Cek Sesi</button>');
        operationalButtons.push('<button type="button" class="btn btn-outline-warning btn-sm text-left text-nowrap w-100" onclick="controlDeviceSession(' + Number(device.id) + ', \'restart\')"><i class="fas fa-redo mr-1"></i>Restart Sesi</button>');

        configButtons.push('<button type="button" class="btn btn-outline-dark btn-sm text-left text-nowrap w-100" onclick="configureDeviceWarmup(' + Number(device.id) + ')"><i class="fas fa-fire mr-1"></i>Atur Warmup</button>');
        configButtons.push('<button type="button" class="btn btn-outline-secondary btn-sm text-left text-nowrap w-100" id="btn-test-device-' + Number(device.id) + '" onclick="testWaDevice(' + Number(device.id) + ')"><i class="fas fa-paper-plane mr-1"></i>Test</button>');
        configButtons.push('<button type="button" class="btn btn-outline-danger btn-sm text-left text-nowrap w-100" onclick="deleteWaDevice(' + Number(device.id) + ')"><i class="fas fa-trash-alt mr-1"></i>Hapus</button>');

        var actions = ''
            + '<div class="d-flex flex-column align-items-stretch w-100" style="min-width: 0; gap: 8px;">'
            + '<div class="small text-muted text-uppercase font-weight-bold text-left">Operasional</div>'
            + operationalButtons.join('')
            + '<div class="small text-muted text-uppercase font-weight-bold text-left mt-1">Konfigurasi</div>'
            + configButtons.join('')
            + '</div>';

        return '' +
            '<tr>' +
                '<td>' + deviceName + '</td>' +
                '<td><code>' + sessionId + '</code></td>' +
                '<td>' + waNumber + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td><span class="badge badge-light" id="wa-device-conn-' + Number(device.id) + '">Mengecek...</span></td>' +
                '<td>' + warmupBadge + '</td>' +
                '<td class="text-right align-middle" style="min-width: 210px;">' + actions + '</td>' +
            '</tr>';
    }).join('');
}

function resolveConnectionBadge(status) {
    var normalized = String(status || '').toLowerCase();
    if (normalized === 'loading') {
        return { cls: 'light', text: 'Mengecek...' };
    }
    if (normalized === 'connected') {
        return { cls: 'success', text: 'Connected' };
    }
    if (normalized === 'connecting') {
        return { cls: 'info', text: 'Proses Login' };
    }
    if (normalized === 'awaiting_qr' || normalized === 'idle' || normalized === 'stopped') {
        return { cls: 'warning', text: 'Belum Scan' };
    }
    if (normalized === 'disconnected' || normalized === 'error') {
        return { cls: 'danger', text: 'Disconnected' };
    }

    return { cls: 'secondary', text: 'Tidak diketahui' };
}

function updateDeviceConnectionBadge(deviceId, status) {
    var badge = document.getElementById('wa-device-conn-' + Number(deviceId));
    if (!badge) {
        return;
    }

    var resolved = resolveConnectionBadge(status);
    badge.className = 'badge badge-' + resolved.cls;
    badge.textContent = resolved.text;
}

function hydrateDeviceConnectionStatuses(devices, showLoadingState) {
    if (!Array.isArray(devices) || devices.length === 0) {
        return;
    }

    var shouldShowLoading = showLoadingState === true;

    devices.forEach(function (device) {
        if (shouldShowLoading) {
            updateDeviceConnectionBadge(device.id, 'loading');
        }
        var params = new URLSearchParams(Object.assign(getTenantPayload(), { device_id: Number(device.id) }));
        fetchJson('{{ route("tenant-settings.wa-session-control", ["action" => "status"]) }}?' + params.toString())
        .then(function (result) {
            var data = result.data;
            if (!data.success) {
                updateDeviceConnectionBadge(device.id, 'unknown');

                return;
            }

            updateDeviceConnectionBadge(device.id, data?.data?.status || 'unknown');
        })
        .catch(function () {
            updateDeviceConnectionBadge(device.id, 'unknown');
        });
    });
}

function stopDeviceStatusAutoRefresh() {
    if (waDeviceState.statusTimer) {
        clearInterval(waDeviceState.statusTimer);
        waDeviceState.statusTimer = null;
    }
}

function startDeviceStatusAutoRefresh() {
    stopDeviceStatusAutoRefresh();
    waDeviceState.statusTimer = setInterval(function () {
        hydrateDeviceConnectionStatuses(waDeviceState.devices || [], false);
    }, 10000);
}

function setWaDeviceResult(message, type) {
    var result = document.getElementById('wa-device-result');
    if (!result) {
        return;
    }

    result.innerHTML = '<div class="alert alert-' + type + ' py-2 px-3 mb-0">' + escapeHtml(message) + '</div>';
}

function loadWaDevices() {
    var body = document.getElementById('wa-device-table-body');
    if (!body) {
        return Promise.resolve();
    }

    body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Memuat data device...</td></tr>';

    return fetchJson('{{ route("tenant-settings.wa-devices.index") }}?' + new URLSearchParams(getTenantPayload()).toString())
    .then(function (result) {
        var data = result.data;
        if (!data.success) {
            throw new Error(data.message || 'Gagal memuat device.');
        }

        waDeviceState.devices = data.data || [];
        renderWaDeviceTable(data.data || []);
        hydrateDeviceConnectionStatuses(data.data || [], true);
    })
    .catch(function (error) {
        waDeviceState.devices = [];
        body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-3">' + escapeHtml(error.message) + '</td></tr>';
    });
}

function submitWaDeviceForm(event) {
    event.preventDefault();

    var form = document.getElementById('wa-device-form');
    var button = document.getElementById('wa-device-add-button');
    if (!form || !button) {
        return;
    }

    var deviceName = (document.getElementById('wa_device_name')?.value || '').trim();
    var waNumber = (document.getElementById('wa_number')?.value || '').trim();
    var sessionId = (document.getElementById('wa_session_id')?.value || '').trim();
    var isWarmup = !!document.getElementById('wa_is_warmup')?.checked;
    var warmupUntil = (document.getElementById('wa_warmup_until')?.value || '').trim();
    var warmupMaxPerBatchRaw = (document.getElementById('wa_warmup_max_per_batch')?.value || '').trim();
    var warmupMaxPerBatch = Number(warmupMaxPerBatchRaw || '0');

    if (!deviceName) {
        setWaDeviceResult('Nama device wajib diisi.', 'danger');

        return;
    }

    var payload = Object.assign(getTenantPayload(), {
        device_name: deviceName,
        wa_number: waNumber || null,
        session_id: sessionId || null,
        is_warmup: isWarmup,
        warmup_until: warmupUntil || null,
        warmup_max_per_batch: Number.isFinite(warmupMaxPerBatch) && warmupMaxPerBatch >= 0 ? Math.floor(warmupMaxPerBatch) : 0,
    });

    button.disabled = true;
    setWaDeviceResult('Menambahkan device...', 'info');

    fetchJson('{{ route("tenant-settings.wa-devices.store") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(payload),
    })
    .then(function (result) {
        var data = result.data;
        if (!data.success) {
            throw new Error(data.message || 'Gagal menambahkan device.');
        }

        form.reset();
        setWaDeviceResult(data.message || 'Device berhasil ditambahkan.', 'success');

        return loadWaDevices();
    })
    .catch(function (error) {
        setWaDeviceResult(error.message, 'danger');
    })
    .finally(function () {
        button.disabled = false;
    });
}

function resetWaStickySender(event) {
    event.preventDefault();

    var input = document.getElementById('wa_sticky_phone');
    var button = document.getElementById('wa-sticky-reset-button');
    if (!input || !button) {
        return;
    }

    var phone = (input.value || '').trim();
    if (!phone) {
        setWaDeviceResult('Nomor pelanggan wajib diisi untuk reset mapping pengirim.', 'danger');

        return;
    }

    button.disabled = true;
    setWaDeviceResult('Mereset mapping pengirim...', 'info');

    fetchJson('{{ route("tenant-settings.wa-sticky-sender.reset") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(Object.assign(getTenantPayload(), { phone: phone })),
    })
    .then(function (result) {
        var data = result.data;
        if (!data.success) {
            throw new Error(data.message || 'Gagal mereset mapping pengirim.');
        }

        input.value = '';
        setWaDeviceResult(data.message || 'Mapping pengirim berhasil direset.', 'success');
    })
    .catch(function (error) {
        setWaDeviceResult(error.message, 'danger');
    })
    .finally(function () {
        button.disabled = false;
    });
}

function setDefaultWaDevice(deviceId) {
    setWaDeviceResult('Mengubah device default...', 'info');

    fetchJson('{{ route("tenant-settings.wa-devices.default", ["device" => "__DEVICE__"]) }}'.replace('__DEVICE__', String(deviceId)), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(getTenantPayload()),
    })
    .then(function (result) {
        var data = result.data;
        if (!data.success) {
            throw new Error(data.message || 'Gagal mengubah default device.');
        }

        setWaDeviceResult(data.message || 'Default device berhasil diperbarui.', 'success');

        return loadWaDevices();
    })
    .catch(function (error) {
        setWaDeviceResult(error.message, 'danger');
    });
}

var waWarmupState = {
    deviceId: null,
    deviceName: '',
    meta: {},
};

function toDatetimeLocalValue(value) {
    var raw = String(value || '').trim();
    if (!raw) {
        return '';
    }

    var d = new Date(raw);
    if (Number.isNaN(d.getTime())) {
        return '';
    }

    var pad = function (n) { return String(n).padStart(2, '0'); };
    var year = d.getFullYear();
    var month = pad(d.getMonth() + 1);
    var day = pad(d.getDate());
    var hour = pad(d.getHours());
    var minute = pad(d.getMinutes());

    return year + '-' + month + '-' + day + 'T' + hour + ':' + minute;
}

function setWarmupModalAlert(message, type) {
    var alertEl = document.getElementById('wa-warmup-modal-alert');
    if (!alertEl) {
        return;
    }

    if (!message) {
        alertEl.innerHTML = '';

        return;
    }

    alertEl.innerHTML = '<div class="alert alert-' + type + ' py-2 px-3 mb-0">' + escapeHtml(message) + '</div>';
}

function syncWarmupModalInputs() {
    var enabled = !!document.getElementById('wa_warmup_modal_enabled')?.checked;
    var untilEl = document.getElementById('wa_warmup_modal_until');
    var maxEl = document.getElementById('wa_warmup_modal_max');

    if (untilEl) {
        untilEl.disabled = !enabled;
    }

    if (maxEl) {
        maxEl.disabled = !enabled;
    }
}

function renderWarmupHistory(meta) {
    var historyEl = document.getElementById('wa-warmup-history');
    if (!historyEl) {
        return;
    }

    var history = Array.isArray(meta?.warmup_history) ? meta.warmup_history : [];
    if (!history.length) {
        historyEl.innerHTML = 'Belum ada histori.';
        return;
    }

    var rows = history.slice(-5).reverse().map(function (row) {
        var by = row && row.changed_by_name ? String(row.changed_by_name) : 'System';
        var at = row && row.changed_at ? escapeHtml(String(row.changed_at)) : '-';
        var mode = row && row.is_warmup ? 'Warmup ON' : 'Warmup OFF';
        var modeType = row && row.warmup_auto ? ' (Auto)' : '';
        var max = row && row.warmup_max_per_batch ? String(row.warmup_max_per_batch) : '-';
        var until = row && row.warmup_until ? String(row.warmup_until) : '-';

        return '<div class="mb-1">• <strong>' + escapeHtml(mode + modeType) + '</strong> | max ' + escapeHtml(max) + ' | s/d ' + escapeHtml(until) + '<br><span class="text-muted">' + at + ' oleh ' + escapeHtml(by) + '</span></div>';
    });

    historyEl.innerHTML = rows.join('');
}

function updateWarmupEstimate() {
    var estimateEl = document.getElementById('wa-warmup-estimate');
    if (!estimateEl) {
        return;
    }

    var enabled = !!document.getElementById('wa_warmup_modal_enabled')?.checked;
    var maxRaw = String(document.getElementById('wa_warmup_modal_max')?.value || '0').trim();
    var maxValue = Number(maxRaw || '0');

    if (!enabled) {
        estimateEl.innerHTML = 'Mode normal aktif. Device dapat dipakai sesuai rotasi standar.';
        return;
    }

    if (maxRaw === '' || maxValue === 0) {
        estimateEl.innerHTML = 'Estimasi: mode <strong>Auto Warmup</strong> aktif. Sistem menaikkan kapasitas device ini bertahap berdasarkan umur warmup.';
        return;
    }

    var maxBatch = Number.isFinite(maxValue) ? Math.max(1, Math.min(100, Math.floor(maxValue))) : 1;
    estimateEl.innerHTML = 'Estimasi: device ini diprioritaskan maksimal <strong>' + maxBatch + '</strong> pesan per batch blast, lalu trafik dialihkan ke device lain.';
}

function applyWarmupPreset(type) {
    var enabledEl = document.getElementById('wa_warmup_modal_enabled');
    var untilEl = document.getElementById('wa_warmup_modal_until');
    var maxEl = document.getElementById('wa_warmup_modal_max');
    if (!enabledEl || !untilEl || !maxEl) {
        return;
    }

    enabledEl.checked = true;
    var now = new Date();
    var addDays = function (days) {
        var d = new Date(now.getTime());
        d.setDate(d.getDate() + days);
        return toDatetimeLocalValue(d.toISOString());
    };

    if (type === 'baru') {
        maxEl.value = '1';
        untilEl.value = addDays(7);
    } else if (type === 'menengah') {
        maxEl.value = '3';
        untilEl.value = addDays(3);
    } else {
        maxEl.value = '10';
        untilEl.value = '';
    }

    syncWarmupModalInputs();
    updateWarmupEstimate();
}

function openDeviceWarmupModal(deviceId, currentWarmup, currentWarmupUntil, currentMaxPerBatch, deviceName) {
    var selectedDevice = (waDeviceState.devices || []).find(function (item) {
        return Number(item.id) === Number(deviceId);
    });
    var selectedMeta = selectedDevice && typeof selectedDevice.meta === 'object' && selectedDevice.meta !== null
        ? selectedDevice.meta
        : {};

    waWarmupState.deviceId = Number(deviceId);
    waWarmupState.deviceName = String((selectedDevice && selectedDevice.device_name) || deviceName || 'Device');
    waWarmupState.meta = selectedMeta;

    var nameEl = document.getElementById('wa_warmup_device_name');
    var idEl = document.getElementById('wa_warmup_device_id');
    var enabledEl = document.getElementById('wa_warmup_modal_enabled');
    var untilEl = document.getElementById('wa_warmup_modal_until');
    var maxEl = document.getElementById('wa_warmup_modal_max');

    if (!nameEl || !idEl || !enabledEl || !untilEl || !maxEl) {
        setWaDeviceResult('Form warmup tidak tersedia.', 'danger');
        return;
    }

    nameEl.textContent = waWarmupState.deviceName;
    idEl.value = String(waWarmupState.deviceId || '');
    enabledEl.checked = !!(selectedMeta.is_warmup ?? currentWarmup);
    untilEl.value = toDatetimeLocalValue(selectedMeta.warmup_until || currentWarmupUntil);
    maxEl.value = selectedMeta.warmup_auto ? '0' : String(Number(selectedMeta.warmup_max_per_batch || currentMaxPerBatch || 1));
    syncWarmupModalInputs();
    updateWarmupEstimate();
    renderWarmupHistory(selectedMeta);
    setWarmupModalAlert('', 'info');

    if (window.jQuery && window.jQuery('#wa-warmup-modal').modal) {
        window.jQuery('#wa-warmup-modal').modal('show');
    }
}

function saveDeviceWarmupFromModal() {
    var deviceId = Number(document.getElementById('wa_warmup_device_id')?.value || '0');
    var enabled = !!document.getElementById('wa_warmup_modal_enabled')?.checked;
    var untilValue = (document.getElementById('wa_warmup_modal_until')?.value || '').trim();
    var maxValueRaw = (document.getElementById('wa_warmup_modal_max')?.value || '').trim();
    var maxValue = Number(maxValueRaw || '0');
    var saveBtn = document.getElementById('wa-warmup-modal-save-btn');

    if (!deviceId) {
        setWarmupModalAlert('Device tidak valid.', 'danger');
        return;
    }

    if (!Number.isFinite(maxValue) || maxValue < 0 || maxValue > 100) {
        setWarmupModalAlert('Maks pesan per batch harus 0 sampai 100.', 'danger');
        return;
    }

    if (saveBtn) {
        saveBtn.disabled = true;
    }
    setWarmupModalAlert('Menyimpan pengaturan warmup...', 'info');

    fetchJson('{{ route("tenant-settings.wa-devices.warmup", ["device" => "__DEVICE__"]) }}'.replace('__DEVICE__', String(deviceId)), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(Object.assign(getTenantPayload(), {
            is_warmup: enabled,
            warmup_until: untilValue || null,
            warmup_max_per_batch: Math.floor(maxValue),
        })),
    })
    .then(function (result) {
        var data = result.data;
        if (!data.success) {
            throw new Error(data.message || 'Gagal menyimpan warmup.');
        }

        setWarmupModalAlert(data.message || 'Pengaturan warmup berhasil diperbarui.', 'success');
        setWaDeviceResult(data.message || 'Pengaturan warmup berhasil diperbarui.', 'success');
        return loadWaDevices().then(function () {
            if (window.jQuery && window.jQuery('#wa-warmup-modal').modal) {
                setTimeout(function () {
                    window.jQuery('#wa-warmup-modal').modal('hide');
                }, 300);
            }
        });
    })
    .catch(function (error) {
        setWarmupModalAlert(error.message, 'danger');
    })
    .finally(function () {
        if (saveBtn) {
            saveBtn.disabled = false;
        }
    });
}

function configureDeviceWarmup(deviceId) {
    openDeviceWarmupModal(deviceId, false, '', 1, '');
}

function deleteWaDevice(deviceId) {
    if (!window.confirm('Hapus device ini?')) {
        return;
    }

    setWaDeviceResult('Menghapus device...', 'info');

    fetchJson('{{ route("tenant-settings.wa-devices.destroy", ["device" => "__DEVICE__"]) }}'.replace('__DEVICE__', String(deviceId)), {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(getTenantPayload()),
    })
    .then(function (result) {
        var data = result.data;
        if (!data.success) {
            throw new Error(data.message || 'Gagal menghapus device.');
        }

        setWaDeviceResult(data.message || 'Device berhasil dihapus.', 'success');

        return loadWaDevices();
    })
    .catch(function (error) {
        setWaDeviceResult(error.message, 'danger');
    });
}

function testWaDevice(deviceId) {
    var btn = document.getElementById('btn-test-device-' + deviceId);
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Mengirim...'; }
    setWaDeviceResult('Mengirim pesan test...', 'info');

    fetchJson('{{ route("tenant-settings.wa-devices.test", ["device" => "__DEVICE__"]) }}'.replace('__DEVICE__', String(deviceId)), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(getTenantPayload()),
    })
    .then(function (result) {
        var data = result.data;
        setWaDeviceResult(data.message || (data.success ? 'Berhasil.' : 'Gagal.'), data.success ? 'success' : 'danger');
    })
    .catch(function () {
        setWaDeviceResult('Terjadi kesalahan saat mengirim test.', 'danger');
    })
    .finally(function () {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane mr-1"></i>Test'; }
    });
}

function controlDeviceSession(deviceId, action) {
    var actionLabel = action === 'restart' ? 'Merestart sesi device...' : 'Mengecek sesi device...';
    setWaDeviceResult(actionLabel, 'info');

    var requestPayload = Object.assign(getTenantPayload(), { device_id: Number(deviceId) });
    var url = '{{ route("tenant-settings.wa-session-control", ["action" => "__ACTION__"]) }}'.replace('__ACTION__', action);
    var options = {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(requestPayload),
    };

    if (action === 'status') {
        url += '?' + new URLSearchParams(requestPayload).toString();
        options = { method: 'GET' };
    }

    fetchJson(url, options)
    .then(function (result) {
        var data = result.data;
        if (!data.success) {
            throw new Error(data.message || 'Aksi sesi device gagal.');
        }

        setWaDeviceResult(data.message || 'Aksi sesi berhasil.', 'success');
    })
    .catch(function (error) {
        setWaDeviceResult(error.message, 'danger');
    });
}

var waQrState = {
    deviceId: null,
    deviceName: '',
    pollTimer: null,
    countdownTimer: null,
    currentQr: null,
    qrDeadlineMs: 0,
    regenerating: false,
    connectingDeadlineMs: 0,
};

function showQrAlert(message, type) {
    var alertEl = document.getElementById('wa-qr-alert');
    if (!alertEl) {
        return;
    }

    alertEl.innerHTML = '<div class="alert alert-' + type + ' py-2 px-3 mb-0">' + escapeHtml(message) + '</div>';
}

function clearQrRenderer() {
    var wrap = document.getElementById('wa-qr-canvas-wrap');
    var canvas = document.getElementById('wa-qr-canvas');
    var meta = document.getElementById('wa-qr-meta');
    var countdown = document.getElementById('wa-qr-countdown');
    if (!wrap || !canvas || !meta || !countdown) {
        return;
    }

    wrap.classList.add('d-none');
    canvas.innerHTML = '';
    meta.textContent = '';
    countdown.textContent = '';
}

function stopQrPolling() {
    if (waQrState.pollTimer) {
        clearInterval(waQrState.pollTimer);
        waQrState.pollTimer = null;
    }
}

function startQrPolling() {
    stopQrPolling();
    waQrState.pollTimer = setInterval(function () {
        refreshDeviceQrStatus('status', true);
    }, 3500);
}

function stopQrCountdown() {
    if (waQrState.countdownTimer) {
        clearInterval(waQrState.countdownTimer);
        waQrState.countdownTimer = null;
    }
}

function updateQrCountdownText() {
    var countdown = document.getElementById('wa-qr-countdown');
    if (!countdown) {
        return;
    }

    if (!waQrState.currentQr || waQrState.qrDeadlineMs <= 0) {
        countdown.textContent = '';

        return;
    }

    var remainMs = waQrState.qrDeadlineMs - Date.now();
    var remainSec = Math.max(0, Math.ceil(remainMs / 1000));
    countdown.textContent = 'Timer scan: ' + remainSec + ' detik';
}

function autoRegenerateQrIfExpired() {
    if (!waQrState.currentQr || waQrState.qrDeadlineMs <= 0 || waQrState.regenerating) {
        return;
    }

    if (Date.now() < waQrState.qrDeadlineMs) {
        return;
    }

    waQrState.regenerating = true;
    showQrAlert('Waktu scan habis. Generate QR baru...', 'warning');
    waQrState.currentQr = null;
    waQrState.qrDeadlineMs = 0;
    clearQrRenderer();

    refreshDeviceQrStatus('restart', true)
        .finally(function () {
            waQrState.regenerating = false;
        });
}

function startQrCountdown() {
    stopQrCountdown();
    updateQrCountdownText();

    waQrState.countdownTimer = setInterval(function () {
        updateQrCountdownText();
        autoRegenerateQrIfExpired();
    }, 1000);
}

function setQrLockWindow() {
    waQrState.qrDeadlineMs = Date.now() + 15000;
    startQrCountdown();
}

function setConnectingWindow() {
    waQrState.connectingDeadlineMs = Date.now() + 45000;
}

function renderQrCode(qrText) {
    var wrap = document.getElementById('wa-qr-canvas-wrap');
    var canvas = document.getElementById('wa-qr-canvas');
    if (!wrap || !canvas) {
        return;
    }

    canvas.innerHTML = '';

    if (window.QRCode) {
        new QRCode(canvas, {
            text: qrText,
            width: 260,
            height: 260,
            correctLevel: QRCode.CorrectLevel.M,
        });
        wrap.classList.remove('d-none');
    } else {
        wrap.classList.add('d-none');
        showQrAlert('Library QR belum termuat. Silakan refresh halaman.', 'danger');
    }
}

function openQrModal(deviceId, deviceName) {
    waQrState.deviceId = Number(deviceId);
    waQrState.deviceName = String(deviceName || 'Device');
    waQrState.currentQr = null;
    waQrState.qrDeadlineMs = 0;
    waQrState.regenerating = false;
    waQrState.connectingDeadlineMs = 0;

    var deviceNameEl = document.getElementById('wa-qr-device-name');
    if (deviceNameEl) {
        deviceNameEl.textContent = waQrState.deviceName;
    }

    clearQrRenderer();
    showQrAlert('Mengecek status sesi device...', 'info');

    if (window.jQuery && window.jQuery('#wa-qr-modal').modal) {
        window.jQuery('#wa-qr-modal').modal('show');
    }

    refreshDeviceQrStatus('status');
}

function refreshDeviceQrStatus(action, silent) {
    if (!waQrState.deviceId) {
        return Promise.resolve();
    }

    var currentAction = action || 'status';
    if (!silent) {
        showQrAlert(currentAction === 'restart' ? 'Meminta QR baru...' : 'Memuat status sesi...', 'info');
    }

    var requestPayload = Object.assign(getTenantPayload(), { device_id: waQrState.deviceId });
    var url = '{{ route("tenant-settings.wa-session-control", ["action" => "__ACTION__"]) }}'.replace('__ACTION__', currentAction);
    var options = {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(requestPayload),
    };

    if (currentAction === 'status') {
        url += '?' + new URLSearchParams(requestPayload).toString();
        options = { method: 'GET' };
    }

    return fetchJson(url, options)
    .then(function (result) {
        var data = result.data;
        if (!data.success) {
            throw new Error(data.message || 'Gagal memuat status QR device.');
        }

        var payload = data.data || {};
        var status = String(payload.status || '').toLowerCase();
        var qr = payload.qr || null;
        var updatedAt = payload.updated_at || null;
        var meta = document.getElementById('wa-qr-meta');

        if (meta) {
            var statusLabel = status !== '' ? ('Status: ' + status) : 'Status: -';
            meta.textContent = updatedAt ? (statusLabel + ' | Update: ' + updatedAt) : statusLabel;
        }

        if (status === 'connected') {
            stopQrPolling();
            stopQrCountdown();
            showQrAlert('Device berhasil terhubung. Menutup popup...', 'success');
            setTimeout(function () {
                if (window.jQuery && window.jQuery('#wa-qr-modal').modal) {
                    window.jQuery('#wa-qr-modal').modal('hide');
                }
                setWaDeviceResult('Device "' + waQrState.deviceName + '" berhasil terhubung.', 'success');
                loadWaDevices();
            }, 600);

            return;
        }

        if (qr) {
            var incomingQr = String(qr);
            var isFirstQr = !waQrState.currentQr;
            var isDifferentQr = waQrState.currentQr && waQrState.currentQr !== incomingQr;
            var lockActive = Date.now() < waQrState.qrDeadlineMs;

            if (isFirstQr || (!lockActive && isDifferentQr)) {
                waQrState.currentQr = incomingQr;
                renderQrCode(incomingQr);
                setQrLockWindow();
                waQrState.connectingDeadlineMs = 0;
            }

            if (isDifferentQr && lockActive) {
                showQrAlert('QR sedang dikunci 15 detik untuk proses scan. QR baru akan dibuat otomatis jika waktu habis.', 'info');
            } else {
                showQrAlert('QR siap dipindai. Buka WhatsApp > Perangkat Tertaut > Tautkan perangkat.', 'success');
            }

            startQrPolling();

            return;
        }

        if (status === 'connecting') {
            stopQrCountdown();
            waQrState.qrDeadlineMs = 0;
            if (waQrState.connectingDeadlineMs <= 0) {
                setConnectingWindow();
            }

            showQrAlert('QR sudah terbaca. Menunggu proses login WhatsApp selesai...', 'info');

            if (Date.now() > waQrState.connectingDeadlineMs && !waQrState.regenerating) {
                waQrState.regenerating = true;
                showQrAlert('Proses login terlalu lama. Sistem mencoba generate QR baru...', 'warning');
                refreshDeviceQrStatus('restart', true).finally(function () {
                    waQrState.regenerating = false;
                    waQrState.connectingDeadlineMs = 0;
                });
            }

            startQrPolling();

            return;
        }

        if (waQrState.currentQr && Date.now() < waQrState.qrDeadlineMs) {
            showQrAlert('Menunggu hasil scan dari QR aktif...', 'info');

            return;
        }

        if (currentAction === 'restart') {
            showQrAlert('Sesi direstart. Menunggu QR muncul...', 'info');
            startQrPolling();

            return;
        }

        clearQrRenderer();
        showQrAlert('QR belum tersedia. Klik "Generate QR Baru" untuk memunculkan QR.', 'warning');
    })
    .catch(function (error) {
        showQrAlert(error.message, 'danger');
    });
}

function controlWaService(action, silent) {
    var statusEl = document.getElementById('wa-service-status');
    var resultEl = document.getElementById('wa-service-result');

    if (!statusEl || !resultEl) {
        return;
    }

    if (!silent) {
        resultEl.innerHTML = '<div class="alert alert-info py-1 px-2 mb-0"><i class="fas fa-spinner fa-spin mr-1"></i>Memproses...</div>';
    }

    fetchJson('{{ route("tenant-settings.wa-service-control", ["action" => "__ACTION__"]) }}'.replace('__ACTION__', action), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(getTenantPayload())
    })
    .then(function (result) {
        var data = result.data;
        var ok = !!data.success;
        var cls = ok ? 'success' : 'danger';
        resultEl.innerHTML = '<div class="alert alert-' + cls + ' py-1 px-2 mb-0">' + escapeHtml(data.message || (ok ? 'Berhasil' : 'Gagal')) + '</div>';
        if (data.data) {
            var running = data.data.running ? 'RUNNING' : 'STOPPED';
            statusEl.innerHTML = 'Status: <strong>' + running + '</strong>' +
                (data.data.pm2_pid ? ' | PID: ' + escapeHtml(String(data.data.pm2_pid)) : '') +
                (data.data.url ? ' | URL: ' + escapeHtml(String(data.data.url)) : '');
        }
    })
    .catch(error => {
        resultEl.innerHTML = '<div class="alert alert-danger py-1 px-2 mb-0">' + escapeHtml(error.message) + '</div>';
    });
}

function restartWaService() {
    controlWaService('restart', false);
}

document.addEventListener('DOMContentLoaded', function () {
    setupWaWizard();

    var deviceForm = document.getElementById('wa-device-form');
    if (deviceForm) {
        deviceForm.addEventListener('submit', submitWaDeviceForm);
        loadWaDevices().then(function () {
            startDeviceStatusAutoRefresh();
        });
    }

    var stickyResetForm = document.getElementById('wa-sticky-reset-form');
    if (stickyResetForm) {
        stickyResetForm.addEventListener('submit', resetWaStickySender);
    }

    var warmupSaveBtn = document.getElementById('wa-warmup-modal-save-btn');
    if (warmupSaveBtn) {
        warmupSaveBtn.addEventListener('click', saveDeviceWarmupFromModal);
    }

    var warmupToggle = document.getElementById('wa_warmup_modal_enabled');
    if (warmupToggle) {
        warmupToggle.addEventListener('change', function () {
            syncWarmupModalInputs();
            updateWarmupEstimate();
        });
    }

    var warmupMaxInput = document.getElementById('wa_warmup_modal_max');
    if (warmupMaxInput) {
        warmupMaxInput.addEventListener('input', updateWarmupEstimate);
    }

    if ('{{ $activeTab }}' === 'devices') {
        var deviceCard = document.getElementById('wa-device-management-card');
        if (deviceCard) {
            deviceCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    if (window.jQuery) {
        window.jQuery('#wa-qr-modal').on('hidden.bs.modal', function () {
            stopQrPolling();
            stopQrCountdown();
            waQrState.deviceId = null;
            waQrState.deviceName = '';
            waQrState.currentQr = null;
            waQrState.qrDeadlineMs = 0;
            waQrState.regenerating = false;
            waQrState.connectingDeadlineMs = 0;
            clearQrRenderer();
            var qrAlert = document.getElementById('wa-qr-alert');
            if (qrAlert) {
                qrAlert.innerHTML = '';
            }
        });
    }

    window.addEventListener('beforeunload', function () {
        stopDeviceStatusAutoRefresh();
    });

    controlWaService('status', true);
});
</script>

<script>
// ========= Keyword Rules =========
var keywordRulesData = [];

function loadKeywordRules() {
    fetch('{{ route('wa-keyword-rules.index') }}', {headers: {'X-Requested-With': 'XMLHttpRequest'}})
        .then(function(r){ return r.json(); })
        .then(function(data){
            keywordRulesData = data;
            renderKeywordRules(data);
        })
        .catch(function(){ renderKeywordRulesError(); });
}

function renderKeywordRules(rules) {
    var tbody = document.getElementById('keyword-rules-tbody');
    if (!tbody) return;
    if (!rules || rules.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada keyword rule. Klik <strong>Tambah Rule</strong> untuk memulai.</td></tr>';
        return;
    }
    var html = '';
    rules.forEach(function(rule) {
        var kws = (rule.keywords || []).map(function(k){ return '<span class="badge badge-secondary mr-1">'+escHtml(k)+'</span>'; }).join('');
        var statusBadge = rule.is_active
            ? '<span class="badge badge-success">Aktif</span>'
            : '<span class="badge badge-secondary">Nonaktif</span>';
        var preview = rule.reply_text ? escHtml(rule.reply_text.substring(0, 60)) + (rule.reply_text.length > 60 ? '...' : '') : '-';
        html += '<tr>'
            + '<td><span class="badge badge-info">'+rule.priority+'</span></td>'
            + '<td>'+kws+'</td>'
            + '<td><small class="text-muted">'+preview+'</small></td>'
            + '<td>'+statusBadge+'</td>'
            + '<td>'
            + '<button class="btn btn-xs btn-outline-primary mr-1" onclick="editKeywordRule('+rule.id+')"><i class="fas fa-edit"></i></button>'
            + '<button class="btn btn-xs btn-outline-danger" onclick="deleteKeywordRule('+rule.id+')"><i class="fas fa-trash"></i></button>'
            + '</td>'
            + '</tr>';
    });
    tbody.innerHTML = html;
}

function renderKeywordRulesError() {
    var tbody = document.getElementById('keyword-rules-tbody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">Gagal memuat data.</td></tr>';
}

function openKeywordAddModal() {
    document.getElementById('modal-keyword-title').innerText = 'Tambah Keyword Rule';
    document.getElementById('keyword-rule-id').value = '';
    document.getElementById('keyword-rule-keywords').value = '';
    document.getElementById('keyword-rule-reply').value = '';
    document.getElementById('keyword-rule-priority').value = 0;
    document.getElementById('keyword-rule-active').checked = true;
    document.getElementById('keyword-rule-error').classList.add('d-none');
    $('#modal-keyword-add').modal('show');
}

function editKeywordRule(id) {
    var rule = keywordRulesData.find(function(r){ return r.id === id; });
    if (!rule) return;
    document.getElementById('modal-keyword-title').innerText = 'Edit Keyword Rule';
    document.getElementById('keyword-rule-id').value = rule.id;
    document.getElementById('keyword-rule-keywords').value = (rule.keywords || []).join('\n');
    document.getElementById('keyword-rule-reply').value = rule.reply_text || '';
    document.getElementById('keyword-rule-priority').value = rule.priority || 0;
    document.getElementById('keyword-rule-active').checked = !!rule.is_active;
    document.getElementById('keyword-rule-error').classList.add('d-none');
    $('#modal-keyword-add').modal('show');
}

function saveKeywordRule() {
    var id = document.getElementById('keyword-rule-id').value;
    var keywordsRaw = document.getElementById('keyword-rule-keywords').value;
    var keywords = keywordsRaw.split('\n').map(function(k){ return k.trim(); }).filter(function(k){ return k !== ''; });
    var replyText = document.getElementById('keyword-rule-reply').value.trim();
    var priority = parseInt(document.getElementById('keyword-rule-priority').value) || 0;
    var isActive = document.getElementById('keyword-rule-active').checked;

    var errEl = document.getElementById('keyword-rule-error');
    errEl.classList.add('d-none');

    if (keywords.length === 0) { errEl.innerText = 'Minimal satu keyword.'; errEl.classList.remove('d-none'); return; }
    if (replyText === '') { errEl.innerText = 'Balasan bot tidak boleh kosong.'; errEl.classList.remove('d-none'); return; }

    var url = id ? '/wa-keyword-rules/' + id : '/wa-keyword-rules';
    var method = id ? 'PUT' : 'POST';

    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ keywords: keywords, reply_text: replyText, priority: priority, is_active: isActive }),
    })
    .then(function(r){ return r.json().then(function(data){ return {ok: r.ok, data: data}; }); })
    .then(function(res){
        if (!res.ok) {
            var msg = res.data.message || 'Gagal menyimpan.';
            errEl.innerText = msg; errEl.classList.remove('d-none');
            return;
        }
        $('#modal-keyword-add').modal('hide');
        loadKeywordRules();
    })
    .catch(function(){ errEl.innerText = 'Terjadi kesalahan jaringan.'; errEl.classList.remove('d-none'); });
}

function deleteKeywordRule(id) {
    if (!confirm('Hapus keyword rule ini?')) return;
    fetch('/wa-keyword-rules/' + id, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
    .then(function(r){ if (r.ok) loadKeywordRules(); });
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.querySelector('[data-target="#modal-keyword-add"]') && document.querySelector('[data-target="#modal-keyword-add"]').addEventListener('click', function() {
    openKeywordAddModal();
});

if (document.getElementById('keyword-rules-tbody')) {
    loadKeywordRules();
}
</script>

<script>
// ========= Notifikasi Tiket ke Grup WA =========
(function () {
    var btnLoad = document.getElementById('btn-load-groups');
    var btnClear = document.getElementById('btn-clear-ticket-group');
    var groupsListDiv = document.getElementById('wa-groups-list');
    var groupsContainer = document.getElementById('wa-groups-container');
    var groupDisplay = document.getElementById('wa_ticket_group_display');

    if (!btnLoad) return;

    @if(auth()->user()->isSuperAdmin() && $selectedTenant)
    var groupsUrl = '{{ route('tenant-settings.wa-groups.index', ['tenant_id' => $selectedTenant->id]) }}';
    var saveUrl   = '{{ route('tenant-settings.wa-ticket-group.update', ['tenant_id' => $selectedTenant->id]) }}';
    @else
    var groupsUrl = '{{ route('tenant-settings.wa-groups.index') }}';
    var saveUrl   = '{{ route('tenant-settings.wa-ticket-group.update') }}';
    @endif

    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    btnLoad.addEventListener('click', function () {
        btnLoad.disabled = true;
        btnLoad.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Memuat...';
        groupsContainer.innerHTML = '<p class="text-muted small mb-0"><i class="fas fa-spinner fa-spin mr-1"></i>Sedang memuat daftar grup...</p>';
        groupsListDiv.classList.remove('d-none');

        fetch(groupsUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btnLoad.disabled = false;
                btnLoad.innerHTML = '<i class="fas fa-sync-alt mr-1"></i>Muat Daftar Grup';

                if (!data.success || !data.data || data.data.length === 0) {
                    groupsContainer.innerHTML = '<p class="text-muted small mb-0">' + escHtml(data.message || 'Tidak ada grup yang ditemukan. Pastikan nomor WA sudah bergabung ke grup.') + '</p>';
                    return;
                }

                var html = '';
                data.data.forEach(function (g) {
                    var label = escHtml(g.subject || g.id);
                    var size = g.size ? ' <small class="text-muted">(' + g.size + ' anggota)</small>' : '';
                    html += '<div class="d-flex align-items-center p-1 border-bottom group-item" style="cursor:pointer;" '
                        + 'data-group-id="' + escHtml(g.id) + '" data-group-name="' + escHtml(g.subject || g.id) + '">'
                        + '<i class="fab fa-whatsapp text-success mr-2"></i>'
                        + '<span>' + label + size + '</span>'
                        + '</div>';
                });
                groupsContainer.innerHTML = html;

                groupsContainer.querySelectorAll('.group-item').forEach(function (el) {
                    el.addEventListener('click', function () {
                        var gid = el.dataset.groupId;
                        var gname = el.dataset.groupName;
                        selectGroup(gid, gname);
                    });
                    el.addEventListener('mouseenter', function () { el.style.background = '#e9ecef'; });
                    el.addEventListener('mouseleave', function () { el.style.background = ''; });
                });
            })
            .catch(function (err) {
                btnLoad.disabled = false;
                btnLoad.innerHTML = '<i class="fas fa-sync-alt mr-1"></i>Muat Daftar Grup';
                groupsContainer.innerHTML = '<p class="text-danger small mb-0">Gagal memuat grup: ' + escHtml(String(err)) + '</p>';
            });
    });

    function selectGroup(groupId, groupName) {
        groupDisplay.value = groupName;
        groupsListDiv.classList.add('d-none');

        fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                wa_ticket_group_id: groupId,
                wa_ticket_group_name: groupName,
            }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                window.AppAjax.showToast(data.message || 'Grup berhasil disimpan.', 'success');
                // Tampilkan tombol clear tanpa reload
                if (!document.getElementById('btn-clear-ticket-group')) {
                    var clearBtn = document.createElement('button');
                    clearBtn.type = 'button';
                    clearBtn.className = 'btn btn-outline-danger';
                    clearBtn.id = 'btn-clear-ticket-group';
                    clearBtn.innerHTML = '<i class="fas fa-times"></i>';
                    btnLoad.parentNode.appendChild(clearBtn);
                    clearBtn.addEventListener('click', function () { clearGroup(); });
                }
            } else {
                window.AppAjax.showToast(data.message || 'Gagal menyimpan.', 'danger');
            }
        })
        .catch(function () {
            window.AppAjax.showToast('Gagal menyimpan grup.', 'danger');
        });
    }

    function clearGroup() {
        fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ wa_ticket_group_id: null, wa_ticket_group_name: null }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                groupDisplay.value = '';
                var clearBtn = document.getElementById('btn-clear-ticket-group');
                if (clearBtn) clearBtn.remove();
                window.AppAjax.showToast('Grup notifikasi tiket dihapus.', 'success');
            }
        });
    }

    if (btnClear) {
        btnClear.addEventListener('click', function () { clearGroup(); });
    }
}());
</script>
@endpush
