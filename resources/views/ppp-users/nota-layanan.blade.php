<!DOCTYPE html>
<html lang="id">
@php
    $editingServiceNote = $editingServiceNote ?? null;
    $isEditingServiceNote = $editingServiceNote !== null;
    $logo = $settings ? ($settings->invoice_logo ?: $settings->business_logo) : null;
    $companyName = $settings && $settings->business_name
        ? $settings->business_name
        : ($pppUser->owner ? ($pppUser->owner->company_name ?? $pppUser->owner->name) : '');
    $companyAddress = $settings ? trim((string) ($settings->business_address ?? '')) : '';
    $companyPhone = $settings ? trim((string) ($settings->business_phone ?? '')) : '';
    $footer = $settings ? trim((string) ($settings->invoice_footer ?? '')) : '';
    $serviceType = in_array($pppUser->tipe_service, ['pppoe', 'hotspot'], true) ? $pppUser->tipe_service : 'general';
    $formNoteType = old('note_type', $editingServiceNote?->note_type ?? $notaType);
    $formPreset = $notaTypes[$formNoteType] ?? $notaDefaults;
    $formItemLines = old('item_lines', $editingServiceNote?->item_lines ?? $formPreset['item_lines'] ?? []);
    $bodyFontFamily = $settings?->browserInvoiceFontCssStack() ?? 'Arial, Helvetica, sans-serif';

    if (! is_array($formItemLines)) {
        $formItemLines = $formPreset['item_lines'] ?? [];
    }

    while (count($formItemLines) < 4) {
        $formItemLines[] = ['label' => '', 'amount' => 0];
    }

    $formDocumentTitle = old('document_title', $editingServiceNote?->document_title ?? $formPreset['document_title']);
    $formSummaryTitle = old('summary_title', $editingServiceNote?->summary_title ?? $formPreset['summary_title']);
    $formDocumentNumber = old('document_number', $editingServiceNote?->document_number ?? $defaultDocumentNumber);
    $formNoteDate = old('note_date', $editingServiceNote?->note_date?->toDateString() ?? now()->toDateString());
    $formPaymentMethod = old('payment_method', $editingServiceNote?->payment_method ?? 'cash');
    $formShowServiceSection = (bool) old('show_service_section', $editingServiceNote?->show_service_section ?? $formPreset['show_service_section'] ?? true);
    $formNotes = old('notes', $editingServiceNote?->notes ?? $formPreset['notes'] ?? '');
    $formFooter = old('footer', $editingServiceNote?->footer ?? $footer);
    $formAction = $isEditingServiceNote
        ? route('service-notes.update', $editingServiceNote)
        : route('ppp-users.service-notes.store', $pppUser);
    $panelTitle = $isEditingServiceNote ? 'Edit Nota Belum Lunas' : 'Simpan Nota';
    $panelSubtitle = $isEditingServiceNote
        ? 'Perbarui nota yang masih menunggu pembayaran atau konfirmasi transfer.'
        : 'Template cetak memakai layout nota lama agar tetap cocok dengan kertas yang sekarang dipakai.';
    $submitButtonLabel = $isEditingServiceNote ? 'Update Nota & Cetak' : 'Simpan Nota & Cetak';
    $transferAccounts = $paymentBankAccounts
        ->map(fn ($bankAccount): array => [
            'bank_name' => $bankAccount->bank_name,
            'account_number' => $bankAccount->account_number,
            'account_name' => $bankAccount->account_name,
            'branch' => $bankAccount->branch,
        ])
        ->values()
        ->all();
    $hasTransferDestination = $transferAccounts !== [];
    $previewRows = collect($formItemLines)
        ->map(fn (array $item): array => [
            'label' => trim((string) ($item['label'] ?? '')),
            'amount' => (float) ($item['amount'] ?? 0),
        ])
        ->filter(fn (array $item): bool => $item['label'] !== '' || $item['amount'] > 0)
        ->values();

    if ($previewRows->isEmpty()) {
        $previewRows = collect([['label' => 'Biaya Layanan', 'amount' => 0]]);
    }

    $previewTotal = (float) $previewRows->sum('amount');
    $noteMetaRows = [
        ['label' => 'No. Nota', 'value' => $formDocumentNumber],
        ['label' => 'No. Pelanggan', 'value' => $pppUser->customer_id],
        ['label' => 'Metode Bayar', 'value' => strtoupper($formPaymentMethod)],
    ];
    $noteCustomerRows = [
        ['label' => 'Nama', 'value' => $pppUser->customer_name],
        ['label' => 'Alamat', 'value' => $pppUser->alamat],
        ['label' => 'No. HP', 'value' => $pppUser->nomor_hp],
    ];
    $noteServiceRows = [
        ['label' => 'Paket', 'value' => $pppUser->profile?->name ?: '-'],
        ['label' => 'Username', 'value' => $pppUser->username ?: '-'],
        ['label' => 'IP Static', 'value' => $pppUser->ip_static],
        ['label' => 'ODP / POP', 'value' => $pppUser->odp_pop],
    ];
    $noteAmountRows = $previewRows
        ->map(fn (array $item): array => [
            'label' => $item['label'],
            'value' => number_format($item['amount'], 2, ',', '.'),
        ])
        ->all();
    $noteAmountRows[] = [
        'label' => $formPaymentMethod === 'transfer' ? 'Total Tagihan' : 'Total Dibayar',
        'value' => number_format($previewTotal, 2, ',', '.'),
        'row_class' => 'total-row',
        'value_attr' => 'id="previewTotal"',
    ];
@endphp
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $notaDefaults['label'] }} - {{ $pppUser->customer_name }}</title>
    @include('service-notes.partials.legacy-style')
    <style>
        body {
            font-family: {!! $bodyFontFamily !!};
        }

        body {
            background: #e2e8f0;
            color: #0f172a;
        }

        .workspace {
            max-width: 1120px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 380px minmax(0, 1fr);
            gap: 20px;
            align-items: start;
        }

        .panel,
        .preview-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
        }

        .panel {
            padding: 18px;
            position: sticky;
            top: 20px;
        }

        .preview-card {
            padding: 14px;
        }

        .panel-title {
            margin: 0 0 4px;
            font-size: 20px;
            font-weight: 700;
        }

        .panel-subtitle {
            margin: 0 0 18px;
            color: #475569;
            font-size: 13px;
        }

        .flash,
        .error-box {
            margin-bottom: 16px;
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 13px;
        }

        .flash {
            background: #ecfeff;
            border: 1px solid #99f6e4;
            color: #115e59;
        }

        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        .error-box ul {
            margin: 6px 0 0;
            padding-left: 18px;
        }

        .control-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
        }

        .control-label {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .control-input,
        .control-textarea,
        .control-select,
        .item-row input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 14px;
            color: #0f172a;
            background: #fff;
            outline: none;
        }

        .control-textarea {
            min-height: 84px;
            resize: vertical;
        }

        .control-input:focus,
        .control-textarea:focus,
        .control-select:focus,
        .item-row input:focus {
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.14);
        }

        .preset-hint,
        .helper-text {
            color: #475569;
            font-size: 12px;
        }

        .preset-hint {
            margin: -6px 0 0;
        }

        .item-editor {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .item-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 120px;
            gap: 10px;
        }

        .panel-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .action-button {
            border: 0;
            border-radius: 12px;
            padding: 11px 16px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .action-button-primary {
            background: linear-gradient(135deg, #0f766e, #14b8a6);
            color: #fff;
        }

        .action-button-secondary {
            background: #e2e8f0;
            color: #0f172a;
        }

        @media (max-width: 960px) {
            .workspace {
                grid-template-columns: 1fr;
            }

            .panel {
                position: static;
            }
        }

        @media (max-width: 640px) {
            .item-row {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            body {
                background: #fff;
            }

            .workspace {
                display: block;
                max-width: none;
                padding: 0;
            }

            .panel {
                display: none !important;
            }

            .preview-card {
                box-shadow: none;
                border-radius: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
<div class="workspace">
    <form method="POST" action="{{ $formAction }}" class="panel" id="serviceNoteForm">
        @csrf
        @if ($isEditingServiceNote)
            @method('PUT')
        @endif
        <input type="hidden" name="service_type" value="{{ $serviceType }}">
        <input type="hidden" name="show_service_section" id="inputShowServiceSection" value="{{ $formShowServiceSection ? '1' : '0' }}">
        <input type="hidden" name="item_lines" id="inputItemLinesPayload">

        <h1 class="panel-title">{{ $panelTitle }}</h1>
        <p class="panel-subtitle">{{ $panelSubtitle }}</p>

        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="error-box">
                Validasi belum lengkap.
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="control-group">
            <label class="control-label" for="inputNotaType">Jenis Nota</label>
            <select id="inputNotaType" class="control-select" name="note_type">
                @foreach ($notaTypes as $key => $preset)
                    <option value="{{ $key }}" @selected($formNoteType === $key)>{{ $preset['label'] }}</option>
                @endforeach
            </select>
            <p class="preset-hint" id="presetDescription">{{ $formPreset['description'] }}</p>
        </div>

        <div class="control-group">
            <label class="control-label" for="inputDocumentTitle">Judul Nota</label>
            <input id="inputDocumentTitle" class="control-input" type="text" name="document_title" value="{{ $formDocumentTitle }}" required>
        </div>

        <div class="control-group">
            <label class="control-label" for="inputSummaryTitle">Judul Rincian</label>
            <input id="inputSummaryTitle" class="control-input" type="text" name="summary_title" value="{{ $formSummaryTitle }}" required>
        </div>

        <div class="control-group">
            <label class="control-label" for="inputDocumentNumber">Nomor Nota</label>
            <input id="inputDocumentNumber" class="control-input" type="text" name="document_number" value="{{ $formDocumentNumber }}" required>
        </div>

        <div class="control-group">
            <label class="control-label" for="inputNoteDate">Tanggal Nota</label>
            <input id="inputNoteDate" class="control-input" type="date" name="note_date" value="{{ $formNoteDate }}" required>
        </div>

        <div class="control-group">
            <label class="control-label" for="inputPaymentMethod">Metode Pembayaran</label>
            <select id="inputPaymentMethod" class="control-select" name="payment_method" required>
                <option value="cash" @selected($formPaymentMethod === 'cash')>Cash</option>
                <option value="transfer" @selected($formPaymentMethod === 'transfer')>Transfer</option>
                <option value="lainnya" @selected($formPaymentMethod === 'lainnya')>Lainnya</option>
            </select>
            <span class="helper-text" id="paymentMethodHint">Pembayaran cash langsung masuk pendapatan. Jika memilih transfer, nota akan dibuat sebagai menunggu konfirmasi sampai dana diterima.</span>
            @if (! $hasTransferDestination)
                <span class="helper-text text-danger" id="transferDestinationWarning">Rekening pembayaran aktif belum tersedia. Jika memilih transfer, tambahkan minimal satu rekening pembayaran terlebih dahulu.</span>
            @endif
        </div>

        <div class="control-group">
            <span class="control-label">Rincian Biaya</span>
            <div class="item-editor" id="itemEditor">
                @foreach ($formItemLines as $index => $item)
                    <div class="item-row">
                        <input type="text" data-item-label value="{{ $item['label'] ?? '' }}" placeholder="Nama item {{ $index + 1 }}">
                        <input type="number" data-item-amount min="0" step="1000" value="{{ (int) round((float) ($item['amount'] ?? 0)) }}" placeholder="Nominal">
                    </div>
                @endforeach
            </div>
            <span class="helper-text">Isi minimal satu item. Item kosong tidak akan disimpan.</span>
        </div>

        <div class="control-group">
            <span class="control-label">Tampilan Nota</span>
            <label class="mb-0 d-flex align-items-center" style="gap:.6rem;font-size:14px;font-weight:500;color:#0f172a;">
                <input type="checkbox" id="toggleServiceSection" value="1" @checked($formShowServiceSection)>
                <span>Tampilkan data layanan di nota utama</span>
            </label>
            <span class="helper-text">Nonaktifkan jika nota dipakai untuk pemasangan kabel, IP kamera, atau pekerjaan yang tidak perlu menampilkan paket, username, dan IP pelanggan.</span>
        </div>

        <div class="control-group">
            <label class="control-label" for="inputNotes">Keterangan Tambahan</label>
            <textarea id="inputNotes" class="control-textarea" name="notes" placeholder="Catatan transaksi atau detail pekerjaan">{{ $formNotes }}</textarea>
        </div>

        <div class="control-group">
            <label class="control-label" for="inputFooter">Catatan Bawah</label>
            <textarea id="inputFooter" class="control-textarea" name="footer" placeholder="Footer yang ikut tercetak">{{ $formFooter }}</textarea>
        </div>

        <div class="panel-actions">
            <button type="submit" class="action-button action-button-primary">{{ $submitButtonLabel }}</button>
            <button type="button" class="action-button action-button-secondary" onclick="window.print()">Cetak Draft</button>
            <button type="button" class="action-button action-button-secondary" onclick="window.close()">Tutup</button>
        </div>
    </form>

    <main class="preview-card">
        @include('service-notes.partials.legacy-document', [
            'documentAttributes' => [
                'document_title' => 'id="previewDocumentTitle"',
                'document_date' => 'id="previewDocumentDate"',
                'summary_title' => 'id="previewSummaryTitle"',
                'amount_rows' => 'id="previewItemRows"',
                'in_words' => 'id="previewInWords"',
                'service_wrapper' => 'id="previewServiceWrapper"',
                'notes_wrapper' => 'id="previewNotesWrapper"',
                'notes' => 'id="previewNotes"',
                'footer_wrapper' => 'id="previewFooterWrapper"',
                'footer' => 'id="previewFooter"',
            ],
            'noteLogo' => $logo ? Storage::url($logo) : null,
            'noteCompanyName' => $companyName,
            'noteCompanyAddress' => $companyAddress,
            'noteCompanyPhone' => $companyPhone,
            'noteDocumentTitle' => $formDocumentTitle,
            'noteDateLabel' => 'Tanggal Nota',
            'noteDateText' => \Carbon\Carbon::parse($formNoteDate)->translatedFormat('d F Y'),
            'noteMetaRows' => $noteMetaRows,
            'noteCustomerRows' => $noteCustomerRows,
            'noteServiceSectionTitle' => 'DATA LAYANAN',
            'noteServiceSectionVisible' => $formShowServiceSection,
            'noteServiceRows' => $noteServiceRows,
            'noteSummaryTitle' => $formSummaryTitle,
            'noteAmountRows' => $noteAmountRows,
            'noteInWordsText' => ucfirst(terbilang((int) round($previewTotal))).' rupiah',
            'noteNotesText' => trim($formNotes),
            'noteFooterText' => $formFooter,
            'noteBottomText' => 'Dokumen dicetak dari nota elektronik - '.$companyName,
        ])

        @if ($hasTransferDestination)
            @include('service-notes.partials.transfer-document', [
                'documentAttributes' => [
                    'sheet_wrapper' => 'id="previewTransferSheet" style="'.($formPaymentMethod === 'transfer' ? '' : 'display:none;').'"',
                    'document_number' => 'id="previewTransferDocumentNumber"',
                    'total' => 'id="previewTransferTotal"',
                ],
                'transferLogo' => $logo ? Storage::url($logo) : null,
                'transferCompanyName' => $companyName,
                'transferCompanyAddress' => $companyAddress,
                'transferCompanyPhone' => $companyPhone,
                'transferDocumentNumber' => $formDocumentNumber,
                'transferCustomerName' => $pppUser->customer_name,
                'transferCustomerId' => $pppUser->customer_id,
                'transferTotalText' => 'Rp '.number_format($previewTotal, 0, ',', '.'),
                'transferAccounts' => $transferAccounts,
                'transferBottomText' => 'Lampiran transfer pembayaran - '.$companyName,
            ])
        @endif
    </main>
</div>

<script>
    const notaPresets = @json($notaTypes);
    const inputNotaType = document.getElementById('inputNotaType');
    const inputDocumentTitle = document.getElementById('inputDocumentTitle');
    const inputSummaryTitle = document.getElementById('inputSummaryTitle');
    const inputDocumentNumber = document.getElementById('inputDocumentNumber');
    const inputNoteDate = document.getElementById('inputNoteDate');
    const inputPaymentMethod = document.getElementById('inputPaymentMethod');
    const inputShowServiceSection = document.getElementById('inputShowServiceSection');
    const inputNotes = document.getElementById('inputNotes');
    const inputFooter = document.getElementById('inputFooter');
    const inputItemLinesPayload = document.getElementById('inputItemLinesPayload');
    const itemEditor = document.getElementById('itemEditor');
    const presetDescription = document.getElementById('presetDescription');
    const paymentMethodHint = document.getElementById('paymentMethodHint');
    const toggleServiceSection = document.getElementById('toggleServiceSection');
    const previewDocumentTitle = document.getElementById('previewDocumentTitle');
    const previewDocumentDate = document.getElementById('previewDocumentDate');
    const previewSummaryTitle = document.getElementById('previewSummaryTitle');
    const previewItemRows = document.getElementById('previewItemRows');
    const previewInWords = document.getElementById('previewInWords');
    const previewServiceWrapper = document.getElementById('previewServiceWrapper');
    const previewTransferSheet = document.getElementById('previewTransferSheet');
    const previewTransferDocumentNumber = document.getElementById('previewTransferDocumentNumber');
    const previewTransferTotal = document.getElementById('previewTransferTotal');
    const previewNotesWrapper = document.getElementById('previewNotesWrapper');
    const previewNotes = document.getElementById('previewNotes');
    const previewFooterWrapper = document.getElementById('previewFooterWrapper');
    const previewFooter = document.getElementById('previewFooter');
    const serviceNoteForm = document.getElementById('serviceNoteForm');
    const hasTransferDestination = @json($hasTransferDestination);

    function getPreset(type) {
        return notaPresets[type] || notaPresets.aktivasi;
    }

    function formatDateLabel(value) {
        if (!value) {
            return '-';
        }

        const parts = value.split('-');

        if (parts.length !== 3) {
            return value;
        }

        const date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    }

    function formatMoney(amount) {
        const normalized = Math.max(0, Number(amount || 0));
        return normalized.toLocaleString('id-ID', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function ucfirst(value) {
        if (!value) {
            return value;
        }

        return value.charAt(0).toUpperCase() + value.slice(1);
    }

    function terbilang(value) {
        const number = Math.max(0, Math.floor(value));
        const satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        if (number < 12) {
            return satuan[number];
        }

        if (number < 20) {
            return terbilang(number - 10) + ' belas';
        }

        if (number < 100) {
            return terbilang(Math.floor(number / 10)) + ' puluh' + (number % 10 ? ' ' + terbilang(number % 10) : '');
        }

        if (number < 200) {
            return 'seratus' + (number % 100 ? ' ' + terbilang(number % 100) : '');
        }

        if (number < 1000) {
            return terbilang(Math.floor(number / 100)) + ' ratus' + (number % 100 ? ' ' + terbilang(number % 100) : '');
        }

        if (number < 2000) {
            return 'seribu' + (number % 1000 ? ' ' + terbilang(number % 1000) : '');
        }

        if (number < 1000000) {
            return terbilang(Math.floor(number / 1000)) + ' ribu' + (number % 1000 ? ' ' + terbilang(number % 1000) : '');
        }

        if (number < 1000000000) {
            return terbilang(Math.floor(number / 1000000)) + ' juta' + (number % 1000000 ? ' ' + terbilang(number % 1000000) : '');
        }

        return terbilang(Math.floor(number / 1000000000)) + ' miliar' + (number % 1000000000 ? ' ' + terbilang(number % 1000000000) : '');
    }

    function readItemRows() {
        const rows = [];
        const itemRows = itemEditor.querySelectorAll('.item-row');

        itemRows.forEach((row) => {
            const label = row.querySelector('[data-item-label]').value.trim();
            const amount = Number(row.querySelector('[data-item-amount]').value || 0);

            if (label !== '' || amount > 0) {
                rows.push({
                    label: label !== '' ? label : 'Biaya Layanan',
                    amount,
                });
            }
        });

        return rows;
    }

    function syncItemPayload() {
        inputItemLinesPayload.value = JSON.stringify(readItemRows());
    }

    function renderMetaRows() {
        const metaTable = previewDocumentDate.closest('.top-info').querySelector('td:last-child table');
        metaTable.innerHTML = ''
            + `<tr><td>No. Nota</td><td>: <strong>${inputDocumentNumber.value.trim() || '-'}</strong></td></tr>`
            + `<tr><td>No. Pelanggan</td><td>: <strong>{{ $pppUser->customer_id ?: '-' }}</strong></td></tr>`
            + `<tr><td>Metode Bayar</td><td>: <strong>${inputPaymentMethod.value.toUpperCase()}</strong></td></tr>`;
    }

    function renderPreview() {
        const selectedPreset = getPreset(inputNotaType.value);
        const rows = readItemRows();
        const effectiveRows = rows.length > 0 ? rows : [{ label: 'Biaya Layanan', amount: 0 }];
        const total = effectiveRows.reduce((carry, item) => carry + item.amount, 0);

        presetDescription.textContent = selectedPreset.description;
        previewDocumentTitle.textContent = inputDocumentTitle.value.trim() || selectedPreset.document_title;
        previewDocumentDate.textContent = formatDateLabel(inputNoteDate.value);
        previewSummaryTitle.textContent = inputSummaryTitle.value.trim() || selectedPreset.summary_title;
        previewItemRows.innerHTML = '';

        effectiveRows.forEach((item) => {
            const tr = document.createElement('tr');
            const labelCell = document.createElement('td');
            const amountCell = document.createElement('td');

            labelCell.className = 'label';
            amountCell.className = 'value';
            labelCell.textContent = item.label;
            amountCell.textContent = formatMoney(item.amount);

            tr.appendChild(labelCell);
            tr.appendChild(amountCell);
            previewItemRows.appendChild(tr);
        });

        const noteText = inputNotes.value.trim();
        const isTransfer = inputPaymentMethod.value === 'transfer';
        const showServiceSection = toggleServiceSection.checked;
        const totalRowLabel = isTransfer ? 'Total Tagihan' : 'Total Dibayar';
        const totalRow = document.createElement('tr');
        totalRow.className = 'total-row';
        totalRow.innerHTML = `<td class="label">${totalRowLabel}</td><td class="value" id="previewTotal">${formatMoney(total)}</td>`;
        previewItemRows.appendChild(totalRow);

        previewInWords.textContent = ucfirst(terbilang(total || 0)) + ' rupiah';
        inputShowServiceSection.value = showServiceSection ? '1' : '0';
        previewServiceWrapper.style.display = showServiceSection ? 'block' : 'none';
        if (previewTransferDocumentNumber) {
            previewTransferDocumentNumber.textContent = inputDocumentNumber.value.trim() || '-';
        }

        if (previewTransferTotal) {
            previewTransferTotal.textContent = 'Rp ' + formatMoney(total).replace(',00', '');
        }

        if (previewTransferSheet) {
            previewTransferSheet.style.display = isTransfer && hasTransferDestination ? 'block' : 'none';
        }
        previewNotes.textContent = noteText;
        previewNotesWrapper.style.display = noteText !== '' ? 'block' : 'none';
        previewFooter.textContent = inputFooter.value.trim();
        previewFooterWrapper.style.display = inputFooter.value.trim() !== '' ? 'block' : 'none';
        paymentMethodHint.textContent = isTransfer
            ? (hasTransferDestination
                ? 'Nota transfer akan disimpan sebagai menunggu konfirmasi dan menambahkan lampiran daftar rekening pembayaran.'
                : 'Rekening pembayaran aktif belum tersedia. Tambahkan minimal satu rekening pembayaran sebelum menyimpan nota transfer.')
            : 'Pembayaran cash langsung masuk ke ringkasan pemasukan teknisi.';
        renderMetaRows();
        syncItemPayload();
    }

    function applyPreset(type) {
        const preset = getPreset(type);
        const itemRows = itemEditor.querySelectorAll('.item-row');

        inputDocumentTitle.value = preset.document_title;
        inputSummaryTitle.value = preset.summary_title;
        toggleServiceSection.checked = Boolean(preset.show_service_section ?? true);
        inputNotes.value = preset.notes || '';

        itemRows.forEach((row, index) => {
            const item = (preset.item_lines || [])[index] || { label: '', amount: 0 };
            row.querySelector('[data-item-label]').value = item.label || '';
            row.querySelector('[data-item-amount]').value = Number(item.amount || 0);
        });

        renderPreview();
    }

    [
        inputDocumentTitle,
        inputSummaryTitle,
        inputDocumentNumber,
        inputNoteDate,
        inputPaymentMethod,
        toggleServiceSection,
        inputNotes,
        inputFooter,
    ].forEach((element) => {
        element.addEventListener('input', renderPreview);
    });

    itemEditor.querySelectorAll('[data-item-label], [data-item-amount]').forEach((element) => {
        element.addEventListener('input', renderPreview);
    });

    inputNotaType.addEventListener('change', function () {
        applyPreset(this.value);
    });

    serviceNoteForm.addEventListener('submit', function () {
        syncItemPayload();
    });

    renderPreview();
</script>
</body>
</html>
