@php
    $transferAccounts = collect($transferAccounts ?? [])
        ->filter(fn (array $account): bool => trim((string) ($account['account_number'] ?? '')) !== '')
        ->values();
    $documentAttributes = $documentAttributes ?? [];
    $sheetWrapperAttr = $documentAttributes['sheet_wrapper'] ?? '';
    $documentNumberAttr = $documentAttributes['document_number'] ?? '';
    $totalAttr = $documentAttributes['total'] ?? '';
@endphp

<div class="sheet sheet-break" {!! $sheetWrapperAttr !!}>
    <div class="nota">
        <div class="header">
            @if ($transferLogo)
                <img src="{{ $transferLogo }}" class="logo" alt="Logo">
            @endif
            <div class="header-text">
                <div class="title">{{ $transferCompanyName }}</div>
                @if ($transferCompanyAddress || $transferCompanyPhone)
                    <div class="subtitle">
                        @if ($transferCompanyAddress)
                            {{ $transferCompanyAddress }}<br>
                        @endif
                        @if ($transferCompanyPhone)
                            HP. {{ $transferCompanyPhone }}
                        @endif
                    </div>
                @endif
            </div>
        </div>
        <hr class="divider">
        <div class="doc-title">DAFTAR REKENING PEMBAYARAN TRANSFER</div>
        <hr class="divider">

        <div class="top-info">
            <table>
                <tr>
                    <td style="width:50%;">
                        <div>No. Nota</div>
                        <div class="bold" {!! $documentNumberAttr !!}>{{ $transferDocumentNumber }}</div>
                    </td>
                    <td style="width:50%;">
                        <table style="width:100%;">
                            <tr>
                                <td>Total Tagihan</td>
                                <td>: <strong {!! $totalAttr !!}>{{ $transferTotalText }}</strong></td>
                            </tr>
                            @if (trim((string) $transferCustomerName) !== '')
                                <tr>
                                    <td>Pelanggan</td>
                                    <td>: <strong>{{ $transferCustomerName }}</strong></td>
                                </tr>
                            @endif
                            @if (trim((string) $transferCustomerId) !== '')
                                <tr>
                                    <td>ID Pelanggan</td>
                                    <td>: <strong>{{ $transferCustomerId }}</strong></td>
                                </tr>
                            @endif
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section-title">PETUNJUK TRANSFER</div>
        <div class="instruction-text">
            Silakan transfer ke salah satu rekening di bawah ini. Mohon sertakan nomor nota atau ID pelanggan saat konfirmasi pembayaran agar proses verifikasi lebih cepat.
        </div>

        <div class="section-title">DAFTAR REKENING</div>
        <table class="transfer-table">
            <tbody>
                @forelse ($transferAccounts as $index => $account)
                    <tr>
                        <td class="transfer-index">{{ $index + 1 }}</td>
                        <td class="transfer-detail">
                            <div class="transfer-bank">{{ $account['bank_name'] ?? '-' }}</div>
                            <div>No. Rekening: <strong>{{ $account['account_number'] ?? '-' }}</strong></div>
                            <div>a/n {{ $account['account_name'] ?? '-' }}</div>
                            @if (trim((string) ($account['branch'] ?? '')) !== '')
                                <div class="transfer-branch">{{ $account['branch'] }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="text-center text-muted">Belum ada rekening transfer yang tersedia.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if (trim((string) $transferBottomText) !== '')
            <div class="note-meta">{{ $transferBottomText }}</div>
        @endif
    </div>
</div>
