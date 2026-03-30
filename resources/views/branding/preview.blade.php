@extends('layouts.admin')

@section('title', 'Preview Branding Rafen')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
                        <div>
                            <h2 class="mb-1 font-weight-bold">Preview Branding Rafen</h2>
                            <p class="text-muted mb-0">Arah utama: operasional ISP yang rapi, cepat, dan jelas.</p>
                        </div>
                        <span class="badge badge-info mt-3 mt-md-0">Concept v1</span>
                    </div>

                    <div class="brand-hero p-4 rounded mb-4">
                        <div class="d-inline-flex align-items-center mb-2" style="gap:.55rem;">
                            <img src="{{ asset('branding/rafen-mark.svg') }}" alt="Rafen Logo" class="brand-logo-preview">
                            <div class="brand-mark mb-0">RAFEN</div>
                        </div>
                        <h4 class="mb-2">Operasional ISP yang rapi, cepat, dan jelas.</h4>
                        <p class="mb-3 text-light">Dari monitoring jaringan, pengelolaan pelanggan, hingga billing dalam satu alur kerja.</p>
                        <div class="d-flex flex-wrap" style="gap:.5rem;">
                            <button class="btn btn-light btn-sm font-weight-bold">Lihat Demo</button>
                            <button class="btn btn-outline-light btn-sm">Dokumen Brand</button>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card h-100 mb-3 mb-lg-0">
                                <div class="card-header">
                                    <h5 class="mb-0">Palet Warna</h5>
                                </div>
                                <div class="card-body">
                                    <div class="color-grid">
                                        <div class="color-item">
                                            <div class="swatch" style="background:#0B2A4A;"></div>
                                            <strong>Navy Core</strong>
                                            <small>#0B2A4A</small>
                                        </div>
                                        <div class="color-item">
                                            <div class="swatch" style="background:#16A3D9;"></div>
                                            <strong>Cyan Signal</strong>
                                            <small>#16A3D9</small>
                                        </div>
                                        <div class="color-item">
                                            <div class="swatch" style="background:#84CC16;"></div>
                                            <strong>Success Lime</strong>
                                            <small>#84CC16</small>
                                        </div>
                                        <div class="color-item">
                                            <div class="swatch" style="background:#DC2626;"></div>
                                            <strong>Alert Red</strong>
                                            <small>#DC2626</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0">Karakter Brand</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <strong>Kompeten</strong>
                                        <p class="text-muted mb-0">Bahasa tegas, keputusan jelas, minim ambigu.</p>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Humanis</strong>
                                        <p class="text-muted mb-0">Tetap natural saat komunikasi pelanggan, tidak terdengar seperti bot.</p>
                                    </div>
                                    <div>
                                        <strong>Terukur</strong>
                                        <p class="text-muted mb-0">Setiap aksi menampilkan status/progres agar tim bisa verifikasi cepat.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Asset Logo</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="logo-item">
                                <img src="{{ asset('branding/rafen-mark.svg') }}" alt="Rafen Mark" class="logo-preview">
                                <small class="text-muted d-block mt-2">Mark (Color)</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="logo-item logo-item-dark">
                                <img src="{{ asset('branding/rafen-mark-mono.svg') }}" alt="Rafen Mark Mono" class="logo-preview">
                                <small class="text-muted d-block mt-2">Mark (Monochrome)</small>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="logo-item">
                                <img src="{{ asset('branding/rafen-wordmark.svg') }}" alt="Rafen Wordmark" class="logo-wordmark-preview">
                                <small class="text-muted d-block mt-2">Wordmark Horizontal</small>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap" style="gap:.55rem;">
                        <a href="{{ asset('branding/rafen-mark.svg') }}" target="_blank" class="btn btn-outline-primary btn-sm">Download Mark SVG</a>
                        <a href="{{ asset('branding/rafen-wordmark.svg') }}" target="_blank" class="btn btn-outline-primary btn-sm">Download Wordmark SVG</a>
                        <a href="{{ asset('branding/rafen-mark-mono.svg') }}" target="_blank" class="btn btn-outline-primary btn-sm">Download Mono SVG</a>
                        <a href="{{ asset('favicon.ico') }}" target="_blank" class="btn btn-outline-secondary btn-sm">Download Favicon ICO</a>
                        <a href="{{ asset('branding/favicon-512.png') }}" target="_blank" class="btn btn-outline-secondary btn-sm">Download Favicon PNG</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Tone of Voice</h5>
                </div>
                <div class="card-body">
                    <div class="p-3 rounded bg-light border mb-3">
                        <small class="text-muted d-block mb-1">Template Registrasi</small>
                        <div>Halo Bapak/Ibu {{ '{name}' }}, layanan internet Anda sudah aktif. Jika ada kendala, tim kami siap bantu kapan saja.</div>
                    </div>
                    <div class="p-3 rounded bg-light border mb-3">
                        <small class="text-muted d-block mb-1">Template Invoice</small>
                        <div>Pengingat ramah: tagihan bulan ini sebesar {{ '{total}' }} dengan jatuh tempo {{ '{due_date}' }}. Terima kasih sudah mempercayakan layanan ke kami.</div>
                    </div>
                    <div class="p-3 rounded bg-light border">
                        <small class="text-muted d-block mb-1">Template Pembayaran</small>
                        <div>Pembayaran Anda sudah kami terima. Layanan tetap aktif, dan invoice dinyatakan lunas.</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100 mt-3 mt-lg-0">
                <div class="card-header">
                    <h5 class="mb-0">Sample UI Komponen</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 d-flex flex-wrap align-items-center" style="gap:.5rem;">
                        <button class="btn btn-primary btn-sm">Primary Action</button>
                        <button class="btn btn-outline-primary btn-sm">Secondary</button>
                        <span class="badge badge-success">Aktif</span>
                        <span class="badge badge-warning text-dark">Menunggu</span>
                        <span class="badge badge-danger">Terisolir</span>
                    </div>

                    <div class="table-responsive border rounded">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Label</th>
                                    <th>Gaya Pesan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge badge-success">OK</span></td>
                                    <td>Sinkron</td>
                                    <td>Data sudah terupdate.</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-warning text-dark">WARN</span></td>
                                    <td>Tertunda</td>
                                    <td>Proses berjalan, mohon tunggu.</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-danger">ERR</span></td>
                                    <td>Gagal</td>
                                    <td>Terjadi kendala, coba ulangi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .brand-hero {
            background: linear-gradient(128deg, #0b2a4a 0%, #124d77 45%, #16a3d9 100%);
            border: 1px solid rgba(255, 255, 255, .22);
            box-shadow: 0 16px 30px rgba(11, 42, 74, .28);
            color: #fff;
        }

        .brand-mark {
            display: inline-block;
            padding: .35rem .65rem;
            border-radius: .4rem;
            font-weight: 800;
            letter-spacing: .09em;
            background: rgba(255, 255, 255, .16);
            border: 1px solid rgba(255, 255, 255, .3);
        }

        .brand-logo-preview {
            width: 2.2rem;
            height: 2.2rem;
            border-radius: .55rem;
            border: 1px solid rgba(255, 255, 255, .32);
            box-shadow: 0 8px 18px rgba(9, 26, 44, .28);
        }

        .color-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .8rem;
        }

        .logo-item {
            border: 1px solid #d7e1ee;
            border-radius: .65rem;
            padding: .8rem;
            background: #fff;
            min-height: 110px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .logo-item-dark {
            background: #0e2136;
            border-color: #213a58;
        }

        .logo-preview {
            width: 58px;
            height: 58px;
            object-fit: contain;
        }

        .logo-wordmark-preview {
            width: min(100%, 320px);
            height: auto;
        }

        .color-item {
            border: 1px solid #d7e1ee;
            border-radius: .65rem;
            padding: .65rem;
            background: #fff;
            display: flex;
            flex-direction: column;
            gap: .2rem;
        }

        .color-item .swatch {
            width: 100%;
            height: 34px;
            border-radius: .45rem;
            border: 1px solid rgba(0, 0, 0, .06);
            margin-bottom: .2rem;
        }

        .color-item small {
            color: #5b6b83;
        }
    </style>
@endsection
