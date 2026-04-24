@extends('layouts.admin')

@section('title', $notaDefaults['label'])

@section('content')
    @php
        $oldItemLines = old('item_lines');
        $itemLines = is_array($oldItemLines) && $oldItemLines !== [] ? $oldItemLines : $notaDefaults['item_lines'];
    @endphp

    <style>
        .service-note-page { display: flex; flex-direction: column; gap: 1rem; }
        .service-note-card { border: 1px solid var(--app-border, #d7e1ee); border-radius: 18px; background: #fff; box-shadow: var(--app-shadow-soft, 0 6px 14px rgba(15, 23, 42, 0.05)); }
        .service-note-header { padding: 1.1rem 1.25rem; border-bottom: 1px solid var(--app-border, #d7e1ee); display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; }
        .service-note-body { padding: 1.1rem 1.25rem; }
        .service-note-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        .service-note-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
        .service-note-field { display: flex; flex-direction: column; gap: .35rem; }
        .service-note-field label { font-size: .78rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: var(--app-text-soft, #5b6b83); }
        .service-note-presets { display: flex; flex-wrap: wrap; gap: .6rem; }
        .service-note-preset { display: inline-flex; align-items: center; gap: .45rem; border-radius: 999px; padding: .45rem .85rem; border: 1px solid var(--app-border, #d7e1ee); background: #fff; color: var(--app-text, #0f172a); text-decoration: none; font-size: .85rem; font-weight: 600; }
        .service-note-preset.is-active { background: #0369a1; border-color: #0369a1; color: #fff; }
        .service-note-items { display: flex; flex-direction: column; gap: .75rem; }
        .service-note-item-row { display: grid; grid-template-columns: minmax(0, 1fr) 180px 48px; gap: .75rem; align-items: end; }
        .service-note-side-note { font-size: .82rem; color: var(--app-text-soft, #5b6b83); }
        .service-note-actions { display: flex; justify-content: space-between; gap: .75rem; flex-wrap: wrap; margin-top: 1rem; }

        @media (max-width: 992px) {
            .service-note-grid,
            .service-note-grid-3 { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .service-note-item-row { grid-template-columns: 1fr; }
        }
    </style>

    <div class="service-note-page">
        <div class="alert alert-info mb-0">
            Nota layanan ini akan disimpan sebagai pendapatan lokal self-hosted. Gunakan untuk aktivasi, pemasangan, perbaikan, atau biaya layanan lain di lapangan.
        </div>

        <div class="service-note-card">
            <div class="service-note-header">
                <div>
                    <h3 class="mb-1">{{ $notaDefaults['label'] }}</h3>
                    <p class="mb-0 text-muted">{{ $pppUser->customer_name }} · {{ $pppUser->customer_id }}</p>
                </div>
                <div class="d-flex flex-wrap" style="gap:.5rem;">
                    <a href="{{ route('service-notes.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-history mr-1"></i> Riwayat Nota
                    </a>
                    <a href="{{ route('ppp-users.edit', $pppUser) }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali ke Pelanggan
                    </a>
                </div>
            </div>
            <div class="service-note-body">
                <div class="service-note-presets mb-3">
                    @foreach ($notaTypes as $key => $preset)
                        <a href="{{ route('ppp-users.nota-layanan', ['pppUser' => $pppUser, 'type' => $key]) }}" class="service-note-preset {{ $notaType === $key ? 'is-active' : '' }}">
                            <i class="fas fa-file-invoice"></i>
                            <span>{{ $preset['label'] }}</span>
                        </a>
                    @endforeach
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <strong>Terdapat kesalahan:</strong>
                        <ul class="mb-0 mt-2 pl-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('ppp-users.service-notes.store', $pppUser) }}" id="serviceNoteForm">
                    @csrf

                    <div class="service-note-grid-3 mb-3">
                        <div class="service-note-field">
                            <label for="note_type">Jenis Nota</label>
                            <select name="note_type" id="note_type" class="form-control">
                                @foreach ($notaTypes as $key => $preset)
                                    <option value="{{ $key }}" @selected(old('note_type', $notaType) === $key)>{{ $preset['label'] }}</option>
                                @endforeach
                            </select>
                            <div class="service-note-side-note" id="presetDescription">{{ $notaDefaults['description'] }}</div>
                        </div>
                        <div class="service-note-field">
                            <label for="document_number">Nomor Nota</label>
                            <input type="text" class="form-control" id="document_number" name="document_number" value="{{ old('document_number', $defaultDocumentNumber) }}">
                        </div>
                        <div class="service-note-field">
                            <label for="note_date">Tanggal Nota</label>
                            <input type="date" class="form-control" id="note_date" name="note_date" value="{{ old('note_date', now()->toDateString()) }}">
                        </div>
                    </div>

                    <div class="service-note-grid mb-3">
                        <div class="service-note-field">
                            <label for="document_title">Judul Dokumen</label>
                            <input type="text" class="form-control" id="document_title" name="document_title" value="{{ old('document_title', $notaDefaults['document_title']) }}">
                        </div>
                        <div class="service-note-field">
                            <label for="summary_title">Judul Ringkasan</label>
                            <input type="text" class="form-control" id="summary_title" name="summary_title" value="{{ old('summary_title', $notaDefaults['summary_title']) }}">
                        </div>
                    </div>

                    <div class="service-note-grid-3 mb-3">
                        <div class="service-note-field">
                            <label for="service_type">Jenis Service</label>
                            <select name="service_type" id="service_type" class="form-control">
                                <option value="general" @selected(old('service_type', $pppUser->tipe_service ?: 'pppoe') === 'general')>General</option>
                                <option value="pppoe" @selected(old('service_type', $pppUser->tipe_service ?: 'pppoe') === 'pppoe')>PPPoE</option>
                                <option value="hotspot" @selected(old('service_type') === 'hotspot')>Hotspot</option>
                            </select>
                        </div>
                        <div class="service-note-field">
                            <label for="payment_method">Metode Bayar</label>
                            <select name="payment_method" id="payment_method" class="form-control">
                                <option value="cash" @selected(old('payment_method', 'cash') === 'cash')>Cash</option>
                                <option value="transfer" @selected(old('payment_method') === 'transfer')>Transfer</option>
                                <option value="lainnya" @selected(old('payment_method') === 'lainnya')>Lainnya</option>
                            </select>
                        </div>
                        <div class="service-note-field">
                            <label>Tampilkan Data Layanan</label>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="show_service_section" name="show_service_section" @checked(old('show_service_section', $notaDefaults['show_service_section']))>
                                <label class="form-check-label" for="show_service_section">
                                    Tampilkan paket, username, IP, dan ODP di nota
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="service-note-card mb-3" style="box-shadow:none;">
                        <div class="service-note-header" style="padding:.9rem 1rem;">
                            <div>
                                <h5 class="mb-1">Item Biaya</h5>
                                <p class="mb-0 text-muted">Minimal satu item biaya harus diisi.</p>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="addItemLine">
                                <i class="fas fa-plus mr-1"></i> Tambah Item
                            </button>
                        </div>
                        <div class="service-note-body">
                            <div class="service-note-items" id="itemLines">
                                @foreach ($itemLines as $index => $itemLine)
                                    <div class="service-note-item-row">
                                        <div class="service-note-field">
                                            <label>Nama Item</label>
                                            <input type="text" class="form-control" name="item_lines[{{ $index }}][label]" value="{{ $itemLine['label'] ?? '' }}">
                                        </div>
                                        <div class="service-note-field">
                                            <label>Nominal</label>
                                            <input type="number" class="form-control" name="item_lines[{{ $index }}][amount]" value="{{ $itemLine['amount'] ?? 0 }}" min="0" step="0.01">
                                        </div>
                                        <button type="button" class="btn btn-outline-danger remove-item-line">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="service-note-grid mb-3">
                        <div class="service-note-field">
                            <label for="notes">Catatan</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4">{{ old('notes', $notaDefaults['notes']) }}</textarea>
                        </div>
                        <div class="service-note-field">
                            <label for="footer">Footer</label>
                            <textarea class="form-control" id="footer" name="footer" rows="4">{{ old('footer', 'Terima kasih atas pembayaran Anda.') }}</textarea>
                        </div>
                    </div>

                    <div class="alert alert-secondary mb-0" id="transferInfo" style="{{ old('payment_method') === 'transfer' ? '' : 'display:none;' }}">
                        @if ($paymentBankAccounts->isEmpty())
                            Rekening pembayaran aktif belum tersedia. Tambahkan minimal satu rekening aktif di pengaturan tenant sebelum membuat nota transfer.
                        @else
                            <strong>Rekening aktif yang akan dilampirkan:</strong>
                            <ul class="mb-0 mt-2 pl-3">
                                @foreach ($paymentBankAccounts as $bankAccount)
                                    <li>{{ $bankAccount->bank_name }} · {{ $bankAccount->account_number }} · {{ $bankAccount->account_name }}{{ $bankAccount->branch ? ' · '.$bankAccount->branch : '' }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="service-note-actions">
                        <div class="service-note-side-note">
                            Pelanggan: <strong>{{ $pppUser->customer_name }}</strong> · Paket: <strong>{{ $pppUser->profile?->name ?? '-' }}</strong>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Simpan dan Cetak Nota
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const notePresets = @json($notaTypes);
        const hasOldInput = @json(session()->hasOldInput());
        const itemLinesContainer = document.getElementById('itemLines');
        const noteTypeSelect = document.getElementById('note_type');
        const paymentMethodSelect = document.getElementById('payment_method');
        const transferInfo = document.getElementById('transferInfo');
        const presetDescription = document.getElementById('presetDescription');
        const documentTitleInput = document.getElementById('document_title');
        const summaryTitleInput = document.getElementById('summary_title');
        const notesInput = document.getElementById('notes');
        const showServiceSectionInput = document.getElementById('show_service_section');

        function nextItemIndex() {
            return itemLinesContainer.querySelectorAll('.service-note-item-row').length;
        }

        function buildItemRow(index, item = {label: '', amount: 0}) {
            const row = document.createElement('div');
            row.className = 'service-note-item-row';
            row.innerHTML = `
                <div class="service-note-field">
                    <label>Nama Item</label>
                    <input type="text" class="form-control" name="item_lines[${index}][label]" value="${item.label ?? ''}">
                </div>
                <div class="service-note-field">
                    <label>Nominal</label>
                    <input type="number" class="form-control" name="item_lines[${index}][amount]" value="${item.amount ?? 0}" min="0" step="0.01">
                </div>
                <button type="button" class="btn btn-outline-danger remove-item-line">
                    <i class="fas fa-trash"></i>
                </button>
            `;

            return row;
        }

        function refreshRemoveButtons() {
            itemLinesContainer.querySelectorAll('.remove-item-line').forEach(function (button) {
                button.onclick = function () {
                    if (itemLinesContainer.querySelectorAll('.service-note-item-row').length === 1) {
                        return;
                    }

                    button.closest('.service-note-item-row').remove();
                };
            });
        }

        function applyPreset(type) {
            const preset = notePresets[type];

            if (!preset) {
                return;
            }

            presetDescription.textContent = preset.description || '';
            documentTitleInput.value = preset.document_title || '';
            summaryTitleInput.value = preset.summary_title || '';
            notesInput.value = preset.notes || '';
            showServiceSectionInput.checked = !!preset.show_service_section;
            itemLinesContainer.innerHTML = '';

            (preset.item_lines || []).forEach(function (item, index) {
                itemLinesContainer.appendChild(buildItemRow(index, item));
            });

            if ((preset.item_lines || []).length === 0) {
                itemLinesContainer.appendChild(buildItemRow(0));
            }

            refreshRemoveButtons();
        }

        function toggleTransferInfo() {
            transferInfo.style.display = paymentMethodSelect.value === 'transfer' ? '' : 'none';
        }

        document.getElementById('addItemLine').addEventListener('click', function () {
            itemLinesContainer.appendChild(buildItemRow(nextItemIndex()));
            refreshRemoveButtons();
        });

        noteTypeSelect.addEventListener('change', function () {
            applyPreset(noteTypeSelect.value);
        });

        paymentMethodSelect.addEventListener('change', toggleTransferInfo);

        refreshRemoveButtons();
        toggleTransferInfo();

        if (!hasOldInput) {
            applyPreset(noteTypeSelect.value);
        }
    </script>
@endsection
