<?php

if (! function_exists('phone_to_wa')) {
    /**
     * Normalisasi nomor HP ke format internasional tanpa '+' untuk wa.me link.
     * 08xxxx   → 628xxxx
     * +628xxxx → 628xxxx
     * 628xxxx  → 628xxxx (tidak berubah)
     */
    function phone_to_wa(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }
        return $digits;
    }
}

if (! function_exists('terbilang')) {
    function terbilang(int $n): string
    {
        $satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan',
                   'sepuluh', 'sebelas', 'dua belas', 'tiga belas', 'empat belas', 'lima belas',
                   'enam belas', 'tujuh belas', 'delapan belas', 'sembilan belas'];

        if ($n === 0) return 'nol';
        if ($n < 20) return $satuan[$n];
        if ($n < 100) return $satuan[(int) ($n / 10)] . ' puluh' . ($n % 10 ? ' ' . $satuan[$n % 10] : '');
        if ($n < 200) return 'seratus' . ($n % 100 ? ' ' . terbilang($n % 100) : '');
        if ($n < 1000) return $satuan[(int) ($n / 100)] . ' ratus' . ($n % 100 ? ' ' . terbilang($n % 100) : '');
        if ($n < 2000) return 'seribu' . ($n % 1000 ? ' ' . terbilang($n % 1000) : '');
        if ($n < 1_000_000) return terbilang((int) ($n / 1000)) . ' ribu' . ($n % 1000 ? ' ' . terbilang($n % 1000) : '');
        if ($n < 1_000_000_000) return terbilang((int) ($n / 1_000_000)) . ' juta' . ($n % 1_000_000 ? ' ' . terbilang($n % 1_000_000) : '');

        return terbilang((int) ($n / 1_000_000_000)) . ' miliar' . ($n % 1_000_000_000 ? ' ' . terbilang($n % 1_000_000_000) : '');
    }
}
