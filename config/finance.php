<?php

return [
    'bhp_rate_percent' => (float) env('FINANCE_BHP_RATE_PERCENT', 0.5),
    'uso_rate_percent' => (float) env('FINANCE_USO_RATE_PERCENT', 1.25),
    'bhp_uso_reference' => [
        'bhp' => 'PP No. 43 Tahun 2023 Lampiran I Angka V.A - tarif 0,50%',
        'uso' => 'PP No. 43 Tahun 2023 Lampiran I Angka V.B - tarif 1,25%',
        'deduction' => 'PP No. 43 Tahun 2023 Pasal 15 ayat (2) (piutang tak tertagih dan biaya interkoneksi/ketersambungan)',
    ],
];
