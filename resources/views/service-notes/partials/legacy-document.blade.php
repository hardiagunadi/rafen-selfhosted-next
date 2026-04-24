@php
    $noteMetaRows = $noteMetaRows ?? [];
    $noteCustomerRows = $noteCustomerRows ?? [];
    $noteServiceRows = $noteServiceRows ?? [];
    $noteServiceSectionVisible = $noteServiceSectionVisible ?? true;
    $noteAmountRows = $noteAmountRows ?? [];
    $noteFooterText = trim((string) ($noteFooterText ?? ''));
    $noteBottomText = trim((string) ($noteBottomText ?? ''));
    $documentAttributes = $documentAttributes ?? [];

    $documentTitleAttr = $documentAttributes['document_title'] ?? '';
    $documentDateAttr = $documentAttributes['document_date'] ?? '';
    $summaryTitleAttr = $documentAttributes['summary_title'] ?? '';
    $amountRowsAttr = $documentAttributes['amount_rows'] ?? '';
    $totalAttr = $documentAttributes['total'] ?? '';
    $inWordsAttr = $documentAttributes['in_words'] ?? '';
    $serviceWrapperAttr = $documentAttributes['service_wrapper'] ?? '';
    $notesWrapperAttr = $documentAttributes['notes_wrapper'] ?? '';
    $notesAttr = $documentAttributes['notes'] ?? '';
    $footerWrapperAttr = $documentAttributes['footer_wrapper'] ?? '';
    $footerAttr = $documentAttributes['footer'] ?? '';
@endphp

<div class="sheet">
    <div class="nota">
        <div class="header">
            @if ($noteLogo)
                <img src="{{ $noteLogo }}" class="logo" alt="Logo">
            @endif
            <div class="header-text">
                <div class="title">{{ $noteCompanyName }}</div>
                @if ($noteCompanyAddress || $noteCompanyPhone)
                    <div class="subtitle">
                        @if ($noteCompanyAddress)
                            {{ $noteCompanyAddress }}<br>
                        @endif
                        @if ($noteCompanyPhone)
                            HP. {{ $noteCompanyPhone }}
                        @endif
                    </div>
                @endif
            </div>
        </div>
        <hr class="divider">
        <div class="doc-title" {!! $documentTitleAttr !!}>{{ $noteDocumentTitle }}</div>
        <hr class="divider">
        <div class="top-info">
            <table>
                <tr>
                    <td style="width:50%;">
                        <div>{{ $noteDateLabel }}</div>
                        <div class="bold" {!! $documentDateAttr !!}>{{ $noteDateText }}</div>
                    </td>
                    <td style="width:50%;">
                        <table style="width:100%;">
                            @foreach ($noteMetaRows as $metaRow)
                                @if (trim((string) ($metaRow['value'] ?? '')) !== '')
                                    <tr>
                                        <td>{{ $metaRow['label'] }}</td>
                                        <td>: <strong>{!! nl2br(e((string) ($metaRow['value'] ?? ''))) !!}</strong></td>
                                    </tr>
                                @endif
                            @endforeach
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section-title">DATA PELANGGAN</div>
        <table class="detail-table">
            @foreach ($noteCustomerRows as $row)
                @if (trim((string) ($row['value'] ?? '')) !== '')
                    <tr>
                        <td class="lbl">{{ $row['label'] }}</td>
                        <td class="sep">:</td>
                        <td class="val">{{ $row['value'] }}</td>
                    </tr>
                @endif
            @endforeach
        </table>

        @if ($noteServiceSectionVisible || $serviceWrapperAttr !== '')
            <div style="{{ $noteServiceSectionVisible ? '' : 'display:none;' }}" {!! $serviceWrapperAttr !!}>
                <div class="section-title">{{ $noteServiceSectionTitle }}</div>
                <table class="detail-table">
                    @foreach ($noteServiceRows as $row)
                        @if (trim((string) ($row['value'] ?? '')) !== '')
                            <tr>
                                <td class="lbl">{{ $row['label'] }}</td>
                                <td class="sep">:</td>
                                <td class="val">{{ $row['value'] }}</td>
                            </tr>
                        @endif
                    @endforeach
                </table>
            </div>
        @endif

        <div class="section-title" {!! $summaryTitleAttr !!}>{{ $noteSummaryTitle }}</div>
        <table class="amount-table" {!! $amountRowsAttr !!}>
            @foreach ($noteAmountRows as $row)
                <tr @if (! empty($row['row_class'])) class="{{ $row['row_class'] }}" @endif>
                    <td class="label">{{ $row['label'] }}</td>
                    <td class="value" @if (! empty($row['value_attr'])) {!! $row['value_attr'] !!} @endif>{{ $row['value'] }}</td>
                </tr>
            @endforeach
        </table>

        <div class="mt-3 bold">
            <em {!! $inWordsAttr !!}>{{ $noteInWordsText }}</em>
        </div>

        @if (trim((string) $noteNotesText) !== '' || $notesWrapperAttr !== '' || $notesAttr !== '')
            <div class="mt-3" style="font-size:10px;{{ trim((string) $noteNotesText) === '' ? ' display:none;' : '' }}" {!! $notesWrapperAttr !!}>
                <span {!! $notesAttr !!}>{{ $noteNotesText }}</span>
            </div>
        @endif

        <div class="mt-3" style="font-size:10px; text-align:center;{{ $noteFooterText === '' ? ' display:none;' : '' }}" {!! $footerWrapperAttr !!}>
            <span {!! $footerAttr !!}>{{ $noteFooterText }}</span>
        </div>

        @if ($noteBottomText !== '')
            <div class="note-meta">{{ $noteBottomText }}</div>
        @endif
    </div>
</div>
