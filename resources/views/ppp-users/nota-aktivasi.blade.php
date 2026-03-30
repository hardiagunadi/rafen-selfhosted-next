<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Aktivasi - {{ $pppUser->customer_name }}</title>
@verbatim
    <style>
        @page {
            size: 140mm 120mm;
            margin: 5mm 5mm;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #000;
        }

        .sheet {
            width: 100%;
        }

        .nota {
            width: 100%;
            box-sizing: border-box;
        }

        .header {
            display: flex;
            align-items: center;
            margin-bottom: 3px;
            margin-top: 3px;
        }

        .logo {
            width: 55px;
        }

        .header-text {
            flex: 1;
            text-align: center;
        }

        .header-text .title {
            font-weight: bold;
            font-size: 15px;
        }

        .header-text .subtitle {
            font-size: 12px;
        }

        .divider {
            border: none;
            border-top: 2px solid #000;
            margin: 3px 0 2px;
        }

        .doc-title {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            letter-spacing: 1px;
            margin-bottom: 3px;
        }

        .top-info table {
            width: 100%;
            font-size: 12px;
        }

        .top-info td {
            vertical-align: top;
            padding: 0;
        }

        .bold { font-weight: bold; }
        .text-right { text-align: right; }
        .mt-3 { margin-top: 3px; }

        .section-title {
            font-weight: bold;
            font-size: 12px;
            border-bottom: 1px solid #000;
            margin-top: 4px;
            margin-bottom: 2px;
            padding-bottom: 0;
        }

        .detail-table {
            width: 100%;
            font-size: 12px;
        }

        .detail-table td {
            padding: 0;
            vertical-align: top;
        }

        .detail-table .lbl {
            width: 42%;
            color: #333;
        }

        .detail-table .sep {
            width: 4%;
        }

        .detail-table .val {
            width: 54%;
            font-weight: bold;
        }

        .amount-table {
            width: 100%;
            margin-top: 2px;
            font-size: 12px;
        }

        .amount-table td {
            padding: 1px 0;
        }

        .amount-table .label {
            width: 60%;
        }

        .amount-table .value {
            width: 40%;
            text-align: right;
            padding-right: 15px;
        }

        .total-row {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            font-weight: bold;
        }

        .ttd-area {
            margin-top: 4px;
            display: flex;
            justify-content: space-between;
            font-size: 12px;
        }

        .ttd-box {
            text-align: center;
            width: 44%;
        }

        .ttd-line {
            margin-top: 18px;
            border-top: 1px solid #000;
            padding-top: 2px;
        }

        .no-print {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        @media print {
            .no-print { display: none !important; }
        }
    </style>
@endverbatim
</head>
<body>

@php
    $settings  = $settings ?? null;
    $logo      = $settings ? ($settings->invoice_logo ?: $settings->business_logo) : null;
    $coName    = $settings && $settings->business_name
                    ? $settings->business_name
                    : ($pppUser->owner ? ($pppUser->owner->company_name ?? $pppUser->owner->name) : '');
    $coAddr    = $settings ? ($settings->business_address ?? '') : '';
    $coPhone   = $settings ? ($settings->business_phone ?? '') : '';
    $footer    = $settings ? ($settings->invoice_footer ?? '') : '';

    $tglAktivasiDefault = now()->format('Y-m-d');
    $tglAktivasi = now()->translatedFormat('d F Y');
    $totalBiaya  = (float)($pppUser->biaya_instalasi ?? 0);
    $grandTotal  = $totalBiaya;
    $terbilang   = ucfirst(terbilang((int) $grandTotal));
    $profil      = $pppUser->profile ? $pppUser->profile->name : '-';
    $tipeService = $pppUser->tipe_service ?? 'PPP';
@endphp

<div class="no-print">
    <label style="margin:0;font-size:13px;font-weight:bold;">Tanggal Aktivasi:</label>
    <input type="date" id="inputTglAktivasi" value="{{ $tglAktivasiDefault }}"
        style="border:1px solid #ccc;border-radius:4px;padding:3px 8px;font-size:13px;"
        oninput="updateTanggal(this.value)">
    <label style="margin:0 0 0 12px;font-size:13px;font-weight:bold;">Biaya Aktivasi (Rp):</label>
    <input type="number" id="inputBiaya" value="{{ (int)$totalBiaya }}" min="0" step="1000"
        style="border:1px solid #ccc;border-radius:4px;padding:3px 8px;font-size:13px;width:130px;"
        oninput="updateBiaya(this.value)">
    <button onclick="window.print()" style="margin-left:8px;">&#128438; Cetak</button>
    <button onclick="window.close()">Tutup</button>
</div>

@php
$notaHtml = ''; // placeholder, kita render langsung dua kali via blade
@endphp

<div class="sheet">

    {{-- NOTA KIRI --}}
    <div class="nota">
        <div class="header">
            @if($logo)
                <img src="{{ Storage::url($logo) }}" class="logo" alt="Logo">
            @endif
            <div class="header-text">
                <div class="title">{{ $coName }}</div>
                @if($coAddr || $coPhone)
                    <div class="subtitle">
                        @if($coAddr){{ $coAddr }}<br>@endif
                        @if($coPhone)HP. {{ $coPhone }}@endif
                    </div>
                @endif
            </div>
        </div>
        <hr class="divider">
        <div class="doc-title">NOTA AKTIVASI PEMASANGAN BARU</div>
        <hr class="divider">
        <div class="top-info">
            <table>
                <tr>
                    <td style="width:50%;">
                        <div>Tanggal Aktivasi</div>
                        <div class="bold nota-tgl">{{ $tglAktivasi }}</div>
                    </td>
                    <td style="width:50%;">
                        <table style="width:100%;">
                            @if($pppUser->customer_id)
                            <tr>
                                <td>No. Pelanggan</td>
                                <td>: <strong>{{ $pppUser->customer_id }}</strong></td>
                            </tr>
                            @endif
                            <tr>
                                <td>Jatuh Tempo</td>
                                <td>: {{ $pppUser->jatuh_tempo ? $pppUser->jatuh_tempo->translatedFormat('d F Y') : '-' }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
        <div class="section-title">DATA PELANGGAN</div>
        <table class="detail-table">
            <tr><td class="lbl">Nama</td><td class="sep">:</td><td class="val">{{ $pppUser->customer_name }}</td></tr>
            @if($pppUser->alamat)
            <tr><td class="lbl">Alamat</td><td class="sep">:</td><td class="val">{{ $pppUser->alamat }}</td></tr>
            @endif
            @if($pppUser->nomor_hp)
            <tr><td class="lbl">No. HP</td><td class="sep">:</td><td class="val">{{ $pppUser->nomor_hp }}</td></tr>
            @endif
        </table>
        <div class="section-title">DATA LAYANAN</div>
        <table class="detail-table">
            <tr><td class="lbl">Paket</td><td class="sep">:</td><td class="val">{{ $profil }}</td></tr>
            <tr><td class="lbl">Username</td><td class="sep">:</td><td class="val">{{ $pppUser->username }}</td></tr>
            @if($pppUser->ip_static)
            <tr><td class="lbl">IP Static</td><td class="sep">:</td><td class="val">{{ $pppUser->ip_static }}</td></tr>
            @endif
            @if($pppUser->odp_pop)
            <tr><td class="lbl">ODP / POP</td><td class="sep">:</td><td class="val">{{ $pppUser->odp_pop }}</td></tr>
            @endif
        </table>
        <div class="section-title">BIAYA AKTIVASI</div>
        <table class="amount-table">
            <tr>
                <td class="label">Biaya Aktivasi</td>
                <td class="value nota-biaya">{{ number_format($totalBiaya, 2, ',', '.') }}</td>
            </tr>
            <tr class="total-row">
                <td class="label">Total Dibayar</td>
                <td class="value nota-total">{{ number_format($grandTotal, 2, ',', '.') }}</td>
            </tr>
        </table>
        <div class="mt-3 bold"><em class="nota-terbilang">{{ $terbilang }} rupiah</em></div>
        @if($footer)
            <div class="mt-3" style="font-size:10px; text-align:center;">{{ $footer }}</div>
        @endif
        <div style="font-size:9px; text-align:right; margin-top:4px; color:#555; font-style:italic;">
            Dokumen dicetak dari nota elektronik - {{ $coName }}
        </div>
    </div>


</div>

<script>
    var bulanId = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

    function updateTanggal(val) {
        if (!val) return;
        var parts = val.split('-');
        if (parts.length !== 3) return;
        var tgl = parseInt(parts[2], 10) + ' ' + bulanId[parseInt(parts[1], 10) - 1] + ' ' + parts[0];
        document.querySelectorAll('.nota-tgl').forEach(function(el){ el.textContent = tgl; });
    }

    function formatRp(n) {
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',00';
    }

    function terbilangJs(n) {
        var satuan = ['','satu','dua','tiga','empat','lima','enam','tujuh','delapan','sembilan',
                      'sepuluh','sebelas','dua belas','tiga belas','empat belas','lima belas',
                      'enam belas','tujuh belas','delapan belas','sembilan belas'];
        var puluhan = ['','','dua puluh','tiga puluh','empat puluh','lima puluh',
                       'enam puluh','tujuh puluh','delapan puluh','sembilan puluh'];
        if (n === 0) return 'nol';
        if (n < 20) return satuan[n];
        if (n < 100) return puluhan[Math.floor(n/10)] + (n%10 ? ' ' + satuan[n%10] : '');
        if (n < 1000) return (Math.floor(n/100) === 1 ? 'seratus' : satuan[Math.floor(n/100)] + ' ratus')
            + (n%100 ? ' ' + terbilangJs(n%100) : '');
        if (n < 1000000) return (Math.floor(n/1000) === 1 ? 'seribu' : terbilangJs(Math.floor(n/1000)) + ' ribu')
            + (n%1000 ? ' ' + terbilangJs(n%1000) : '');
        if (n < 1000000000) return terbilangJs(Math.floor(n/1000000)) + ' juta'
            + (n%1000000 ? ' ' + terbilangJs(n%1000000) : '');
        return terbilangJs(Math.floor(n/1000000000)) + ' miliar'
            + (n%1000000000 ? ' ' + terbilangJs(n%1000000000) : '');
    }

    function updateBiaya(val) {
        var biaya = parseInt(val, 10) || 0;
        var total = biaya;
        var terb = total > 0 ? terbilangJs(total) : 'nol';
        terb = terb.trim();
        terb = terb.charAt(0).toUpperCase() + terb.slice(1);

        document.querySelectorAll('.nota-biaya').forEach(function(el){ el.textContent = formatRp(biaya); });
        document.querySelectorAll('.nota-total').forEach(function(el){ el.textContent = formatRp(total); });
        document.querySelectorAll('.nota-terbilang').forEach(function(el){ el.textContent = terb + ' rupiah'; });
    }
</script>
</body>
</html>
