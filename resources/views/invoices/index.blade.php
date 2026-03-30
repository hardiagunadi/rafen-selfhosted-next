@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
<div class="alert alert-light border mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:.75rem;">
        <div>
            <h4 class="mb-1">{{ $pageTitle }}</h4>
            <p class="mb-0 text-muted">{{ $pageDescription }}</p>
            @unless($showMonthlyDebtRecap)
            <div class="d-flex align-items-center flex-wrap mt-2" style="gap:.5rem;">
                <span class="badge badge-info">Perpanjangan Bulan Berjalan</span>
                <span class="badge badge-secondary">Invoice Tunggakan</span>
            </div>
            @endunless
        </div>
        <div class="d-flex align-items-center flex-wrap" style="gap:.5rem;">
            <div class="btn-group btn-group-sm" role="group" aria-label="Filter konteks invoice">
                <button
                    type="button"
                    class="btn {{ $selectedInvoiceContext === null ? 'btn-dark' : 'btn-outline-dark' }} btn-filter-invoice-context"
                    data-context=""
                    data-label=""
                >
                    {{ $showMonthlyDebtRecap ? 'Semua Konteks' : 'Semua Invoice Belum Lunas' }}
                </button>
                @foreach($invoiceContextOptions as $contextKey => $contextLabel)
                <button
                    type="button"
                    class="btn {{ $selectedInvoiceContext === $contextKey ? 'btn-dark' : 'btn-outline-dark' }} btn-filter-invoice-context"
                    data-context="{{ $contextKey }}"
                    data-label="{{ $contextLabel }}"
                >
                    {{ $contextLabel }}
                </button>
                @endforeach
            </div>
            @if($showMonthlyDebtRecap)
            <button
                type="button"
                id="btn-clear-due-month"
                class="btn btn-outline-secondary btn-sm {{ $selectedDueMonth ? '' : 'd-none' }}"
            >
                <i class="fas fa-times mr-1"></i>Semua Bulan
            </button>
            @endif
        </div>
    </div>
</div>

<div
    id="invoice-context-active-banner"
    class="alert alert-secondary mb-3 {{ $selectedInvoiceContext ? '' : 'd-none' }}"
>
    Konteks aktif: <strong id="invoice-context-active-label">{{ $selectedInvoiceContextLabel ?? '-' }}</strong>.
</div>

@if($showMonthlyDebtRecap)
<div class="row mb-3">
    <div class="col-md-3 col-sm-6 mb-2">
        <div class="p-3 border rounded h-100 d-flex align-items-center">
            <div class="mr-3 text-warning"><i class="fas fa-file-invoice-dollar fa-2x"></i></div>
            <div class="text-left">
                <div class="small text-uppercase text-muted">Invoice Terhutang</div>
                <div class="h5 mb-0">{{ $unpaidSummary['invoice_count'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-2">
        <div class="p-3 border rounded h-100 d-flex align-items-center">
            <div class="mr-3 text-danger"><i class="fas fa-wallet fa-2x"></i></div>
            <div class="text-left">
                <div class="small text-uppercase text-muted">Total Terhutang</div>
                <div class="h5 mb-0">Rp {{ number_format($unpaidSummary['total_amount'], 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-2">
        <div class="p-3 border rounded h-100 d-flex align-items-center">
            <div class="mr-3 text-info"><i class="fas fa-calendar-alt fa-2x"></i></div>
            <div class="text-left">
                <div class="small text-uppercase text-muted">Bulan Terhutang</div>
                <div class="h5 mb-0">{{ $unpaidSummary['month_count'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-2">
        <div class="p-3 border rounded h-100 d-flex align-items-center">
            <div class="mr-3 text-secondary"><i class="fas fa-history fa-2x"></i></div>
            <div class="text-left">
                <div class="small text-uppercase text-muted">Periode Terlama</div>
                <div class="h5 mb-0">{{ $unpaidSummary['oldest_month_label'] }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h4 class="mb-0">Rekap Invoice Terhutang per Bulan</h4>
    </div>
    <div class="card-body p-0">
        <div
            id="due-month-active-banner"
            class="alert alert-info border-0 rounded-0 mb-0 {{ $selectedDueMonth ? '' : 'd-none' }}"
        >
            Menampilkan invoice terhutang untuk periode <strong id="due-month-active-label">{{ $selectedDueMonthLabel ?? '-' }}</strong>.
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Bulan</th>
                        <th style="width:180px;">Jumlah Invoice</th>
                        <th class="text-right" style="width:220px;">Total Terhutang</th>
                        <th class="text-right" style="width:150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($monthlyDebt as $monthDebt)
                    <tr>
                        <td>{{ $monthDebt['month_label'] }}</td>
                        <td>{{ $monthDebt['invoice_count'] }} invoice</td>
                        <td class="text-right font-weight-bold">Rp {{ number_format($monthDebt['total_amount'], 0, ',', '.') }}</td>
                        <td class="text-right">
                            <button
                                type="button"
                                class="btn btn-sm {{ $selectedDueMonth === $monthDebt['month_key'] ? 'btn-primary' : 'btn-outline-primary' }} btn-filter-due-month"
                                data-month="{{ $monthDebt['month_key'] }}"
                                data-label="{{ $monthDebt['month_label'] }}"
                            >
                                {{ $selectedDueMonth === $monthDebt['month_key'] ? 'Sedang Aktif' : 'Lihat Invoice' }}
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-3">Belum ada invoice terhutang.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem;">
        <h4 class="mb-0">{{ $tableHeading }}</h4>
        <div class="d-flex align-items-center" style="gap:.5rem;">
            <select id="filter-status" class="form-control form-control-sm" style="width:220px;">
                @if($showMonthlyDebtRecap)
                <option value="" @selected($initialStatusFilter === null)>Semua Status</option>
                <option value="unpaid" @selected($initialStatusFilter === 'unpaid')>Belum Bayar (Semua)</option>
                @else
                <option value="unpaid" selected>Semua Invoice Belum Lunas</option>
                @endif
                <option value="active_unpaid">Aktif - Belum Bayar</option>
                <option value="isolated_unpaid">Belum Bayar - Terisolir</option>
                @if($showMonthlyDebtRecap)
                <option value="paid">Lunas</option>
                @endif
            </select>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-toggle="dropdown" title="Pilih otomatis">
                    <i class="fas fa-check-square"></i>
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item btn-auto-check" data-n="5" href="#">Pilih 5 teratas</a>
                    <a class="dropdown-item btn-auto-check" data-n="10" href="#">Pilih 10 teratas</a>
                    <a class="dropdown-item btn-auto-check" data-n="20" href="#">Pilih 20 teratas</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" id="btn-uncheck-all" href="#">Batal semua pilihan</a>
                </div>
            </div>
            <button id="btn-bulk-nota" class="btn btn-secondary btn-sm" disabled>
                <i class="fas fa-receipt mr-1"></i><span>Cetak Nota Terpilih</span>
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="invoice-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="check-all"></th>
                        <th>Invoice</th>
                        <th>Pelanggan</th>
                        <th>Tipe / Paket</th>
                        <th>Tagihan</th>
                        <th>Jatuh Tempo</th>
                        <th style="width:80px;">Status</th>
                        <th class="text-right" style="width:170px;">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    var allowDueMonthFilter = @json($showMonthlyDebtRecap);
    var initialStatusFilter = @json($initialStatusFilter);
    var selectedDueMonth = @json($selectedDueMonth);
    var selectedDueMonthLabel = @json($selectedDueMonthLabel);
    var selectedInvoiceContext = @json($selectedInvoiceContext);
    var selectedInvoiceContextLabel = @json($selectedInvoiceContextLabel);

    function statusBadge(status, type, row) {
        var label = row && row.status_label ? row.status_label : (status === 'paid' ? 'Lunas' : 'Belum Bayar');
        var variant = row && row.status_variant ? row.status_variant : (status === 'paid' ? 'success' : 'warning');

        return '<span class="badge badge-' + variant + '">' + $.fn.dataTable.render.text().display(label) + '</span>';
    }

    function renderAksi(d, type, row) {
        var renew = row.can_renew
            ? ('<button class="btn btn-primary btn-sm mr-1"'
                + ' data-ajax-post="' + row.renew_url + '" data-confirm="Perpanjang layanan tanpa pembayaran?"'
                + ' title="Perpanjang Layanan"><i class="fas fa-bolt"></i></button>')
            : '';
        var pay;
        if (row.has_pending) {
            pay = '<a href="{{ route("payments.pending") }}" class="btn btn-warning btn-sm mr-1" title="Menunggu konfirmasi bukti transfer"><i class="fas fa-clock"></i></a>';
        } else if (row.status === 'paid') {
            pay = '<button class="btn btn-outline-success btn-sm mr-1" disabled title="Tagihan sudah lunas"><i class="fas fa-money-bill-wave"></i></button>';
        } else if (row.can_mark_paid) {
            pay = '<button class="btn btn-success btn-sm mr-1"'
                + ' data-pay-modal="1" data-pay-url="' + row.pay_url + '" data-invoice-number="' + row.invoice_number + '" data-customer-name="' + row.customer_name + '" data-total="' + row.total + '"'
                + ' title="Tandai Lunas"><i class="fas fa-money-bill-wave"></i></button>';
        } else {
            pay = '<button class="btn btn-success btn-sm mr-1"'
                + (row.can_pay ? ' data-pay-modal="1" data-pay-url="' + row.pay_url + '" data-invoice-number="' + row.invoice_number + '" data-customer-name="' + row.customer_name + '" data-total="' + row.total + '"' : ' disabled')
                + ' title="Bayar"><i class="fas fa-credit-card"></i></button>';
        }
        var view = '<a href="' + row.show_url + '" class="btn btn-info btn-sm mr-1" title="Lihat Invoice"><i class="fas fa-eye"></i></a>';
        var nota = row.can_nota
            ? '<a href="' + row.nota_url + '" target="_blank" class="btn btn-secondary btn-sm mr-1" title="Cetak Nota"><i class="fas fa-receipt"></i></a>'
            : '';
        var del = row.can_delete
            ? ('<button class="btn btn-danger btn-sm"'
                + ' data-ajax-delete="' + row.destroy_url + '" data-confirm="Hapus invoice ini?"'
                + '><i class="fas fa-trash"></i></button>')
            : '';
        return '<div class="text-right d-flex justify-content-end" style="gap:2px;">' + renew + pay + view + nota + del + '</div>';
    }

    function updateBulkBtn() {
        var checked = $('#invoice-table tbody input.row-check:checked').length;
        $('#btn-bulk-nota').prop('disabled', checked === 0);
        $('#btn-bulk-nota span').text(checked > 0 ? 'Cetak Nota Terpilih (' + checked + ')' : 'Cetak Nota Terpilih');
    }

    function updateDueMonthUi() {
        $('.btn-filter-due-month').each(function () {
            var month = $(this).data('month');
            var isActive = selectedDueMonth === month;
            $(this)
                .toggleClass('btn-primary', isActive)
                .toggleClass('btn-outline-primary', !isActive)
                .text(isActive ? 'Sedang Aktif' : 'Lihat Invoice');
        });

        $('#btn-clear-due-month').toggleClass('d-none', !selectedDueMonth);
        $('#due-month-active-banner').toggleClass('d-none', !selectedDueMonth);
        $('#due-month-active-label').text(selectedDueMonthLabel || '-');

        $('.btn-filter-invoice-context').each(function () {
            var context = $(this).data('context');
            var isActive = (selectedInvoiceContext || '') === String(context || '');
            $(this)
                .toggleClass('btn-dark', isActive)
                .toggleClass('btn-outline-dark', !isActive);
        });

        $('#invoice-context-active-banner').toggleClass('d-none', !selectedInvoiceContext);
        $('#invoice-context-active-label').text(selectedInvoiceContextLabel || '-');
    }

    function syncDueMonthQuery() {
        var url = new URL(window.location.href);

        if (allowDueMonthFilter && selectedDueMonth) {
            url.searchParams.set('due_month', selectedDueMonth);
        } else {
            url.searchParams.delete('due_month');
        }

        if (selectedInvoiceContext) {
            url.searchParams.set('invoice_context', selectedInvoiceContext);
        } else {
            url.searchParams.delete('invoice_context');
        }

        window.history.replaceState({}, '', url.toString());
    }

    function init() {
        if (!document.getElementById('invoice-table')) return;
        if ($.fn.DataTable.isDataTable('#invoice-table')) return;

        if (initialStatusFilter) {
            $('#filter-status').val(initialStatusFilter);
        }

        var table = $('#invoice-table').DataTable({
            processing: true, serverSide: true,
            ajax: {
                url: '{{ route("invoices.datatable") }}',
                data: function (d) {
                    d.status = $('#filter-status').val();
                    d.due_month = selectedDueMonth || '';
                    d.invoice_context = selectedInvoiceContext || '';
                }
            },
            columns: [
                { data: 'id', orderable: false, render: function(d) {
                    return '<input type="checkbox" class="row-check" value="' + d + '">';
                }},
                { data: 'invoice_number', render: function(d, t, row) {
                    var context = row.invoice_context_label
                        ? '<div class="small mt-1"><span class="badge badge-' + row.invoice_context_variant + '">' + $.fn.dataTable.render.text().display(row.invoice_context_label) + '</span></div>'
                        : '';

                    return '<div class="font-weight-bold">' + d + '</div>'
                         + '<div class="text-muted small">' + row.owner_name + '</div>'
                         + context;
                }},
                { data: 'customer_name', render: function(d, t, row) {
                    return '<div>' + $.fn.dataTable.render.text().display(d) + '</div>'
                         + '<div class="text-muted small">' + (row.customer_id || '') + '</div>';
                }},
                { data: 'tipe_service', render: function(d, t, row) {
                    return d + '<br><small class="text-muted">' + $.fn.dataTable.render.text().display(row.paket_langganan) + '</small>';
                }},
                { data: 'total', render: function(d) { return 'Rp ' + d; }},
                { data: 'due_date' },
                { data: 'status', render: statusBadge, orderable: false },
                { data: null, render: renderAksi, orderable: false },
            ],
            pageLength: 20, stateSave: false,
        });

        updateDueMonthUi();
        syncDueMonthQuery();

        $('#filter-status').on('change', function () { table.ajax.reload(); });

        document.addEventListener('rafen:ajax-success', function () { table.ajax.reload(null, false); });

        $(document).on('click', '.btn-filter-due-month', function () {
            selectedDueMonth = $(this).data('month');
            selectedDueMonthLabel = $(this).data('label');
            $('#filter-status').val('unpaid');
            updateDueMonthUi();
            syncDueMonthQuery();
            table.ajax.reload();
        });

        $('#btn-clear-due-month').on('click', function () {
            selectedDueMonth = null;
            selectedDueMonthLabel = null;
            updateDueMonthUi();
            syncDueMonthQuery();
            table.ajax.reload();
        });

        $(document).on('click', '.btn-filter-invoice-context', function () {
            selectedInvoiceContext = $(this).data('context') || null;
            selectedInvoiceContextLabel = $(this).data('label') || null;
            if (selectedInvoiceContext) {
                $('#filter-status').val('unpaid');
            }
            updateDueMonthUi();
            syncDueMonthQuery();
            table.ajax.reload();
        });

        // Check-all
        $(document).on('change', '#check-all', function () {
            var checked = $(this).prop('checked');
            $('#invoice-table tbody input.row-check').prop('checked', checked);
            updateBulkBtn();
        });

        $(document).on('change', '#invoice-table tbody input.row-check', function () {
            var total = $('#invoice-table tbody input.row-check').length;
            var checkedCount = $('#invoice-table tbody input.row-check:checked').length;
            $('#check-all').prop('indeterminate', checkedCount > 0 && checkedCount < total);
            $('#check-all').prop('checked', checkedCount === total && total > 0);
            updateBulkBtn();
        });

        // Auto-check N teratas
        $(document).on('click', '.btn-auto-check', function (e) {
            e.preventDefault();
            var n = parseInt($(this).data('n'));
            var boxes = $('#invoice-table tbody input.row-check');
            boxes.prop('checked', false);
            boxes.slice(0, n).prop('checked', true);
            var total = boxes.length;
            var checkedCount = boxes.filter(':checked').length;
            $('#check-all').prop('indeterminate', checkedCount > 0 && checkedCount < total);
            $('#check-all').prop('checked', checkedCount === total && total > 0);
            updateBulkBtn();
        });

        // Batal semua pilihan
        $(document).on('click', '#btn-uncheck-all', function (e) {
            e.preventDefault();
            $('#invoice-table tbody input.row-check').prop('checked', false);
            $('#check-all').prop('checked', false).prop('indeterminate', false);
            updateBulkBtn();
        });

        // Reset on page change
        table.on('draw', function () {
            $('#check-all').prop('checked', false).prop('indeterminate', false);
            updateBulkBtn();
        });

        // Bulk print
        $('#btn-bulk-nota').on('click', function () {
            var ids = $('#invoice-table tbody input.row-check:checked').map(function () {
                return $(this).val();
            }).get();
            if (ids.length === 0) return;
            var url = '{{ route("invoices.nota-bulk") }}?ids=' + ids.join(',');
            window.open(url, '_blank');
        });
    }
    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
<!-- Modal Konfirmasi Bayar -->
<div class="modal fade" id="modal-pay" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-money-bill-wave text-success mr-2"></i>Konfirmasi Pembayaran</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="form-pay" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-light border mb-3 p-2">
                        <div class="small text-muted">Invoice</div>
                        <div class="font-weight-bold" id="pay-invoice-number">-</div>
                        <div class="small text-muted mt-1">Pelanggan</div>
                        <div id="pay-customer-name">-</div>
                        <div class="small text-muted mt-1">Total Tagihan</div>
                        <div class="font-weight-bold text-success" id="pay-total" role="button" tabindex="0" title="Klik untuk isi Tunai Diterima" style="cursor: pointer;">-</div>
                    </div>
                    <div class="form-group">
                        <label>Tunai Diterima <span class="text-muted small">(kosongkan jika tidak ada)</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                            <input type="text" id="pay-cash-display" class="form-control money-input" inputmode="numeric" placeholder="0" autocomplete="off">
                            <input type="hidden" name="cash_received" id="pay-cash">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Sudah Transfer <span class="text-muted small">(kosongkan jika tidak ada)</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                            <input type="text" id="pay-transfer-display" class="form-control money-input" inputmode="numeric" placeholder="0" autocomplete="off">
                            <input type="hidden" name="transfer_amount" id="pay-transfer">
                        </div>
                        <small id="pay-sisa-info" class="text-muted"></small>
                    </div>
                    <div class="form-group mb-0">
                        <label>Catatan <span class="text-muted small">(opsional)</span></label>
                        <input type="text" name="payment_note" id="pay-note" class="form-control" placeholder="mis. bayar di depan rumah">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="btn-pay-submit">
                        <i class="fas fa-money-bill-wave mr-1"></i>Tandai Lunas
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    // Handler tombol bayar → buka modal
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-pay-modal]');
        if (!btn) return;
        document.getElementById('pay-invoice-number').textContent = btn.dataset.invoiceNumber || '-';
        document.getElementById('pay-customer-name').textContent = btn.dataset.customerName || '-';
        var rawTotal = parseInt((btn.dataset.total || '0').replace(/\D/g, ''), 10) || 0;
        document.getElementById('pay-total').textContent = 'Rp ' + formatRibuan(rawTotal);
        document.getElementById('pay-total').dataset.raw = rawTotal;
        document.getElementById('pay-cash-display').value = '';
        document.getElementById('pay-transfer-display').value = '';
        document.getElementById('pay-cash').value = '';
        document.getElementById('pay-transfer').value = '';
        document.getElementById('pay-note').value = '';
        document.getElementById('pay-sisa-info').textContent = '';
        document.getElementById('form-pay').action = btn.dataset.payUrl;
        $('#modal-pay').modal('show');
    });

    // Format ribuan helper
    function formatRibuan(n) {
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function parseRibuan(s) {
        return parseInt(s.replace(/\./g, '').replace(/,/g, ''), 10) || 0;
    }

    // Format money inputs
    function attachMoneyInput(displayId, hiddenId) {
        var display = document.getElementById(displayId);
        var hidden  = document.getElementById(hiddenId);
        display.addEventListener('input', function () {
            var digits = this.value.replace(/\D/g, '');
            var num    = parseInt(digits, 10) || 0;
            this.value  = num ? formatRibuan(num) : '';
            hidden.value = num || '';
            updateSisaInfo();
        });
        display.addEventListener('keydown', function (e) {
            // Allow: backspace, delete, tab, escape, arrows, home, end
            if ([8,9,27,46,37,38,39,40,35,36].indexOf(e.keyCode) !== -1) return;
            // Allow Ctrl/Cmd+A/C/V/X
            if ((e.ctrlKey || e.metaKey) && [65,67,86,88].indexOf(e.keyCode) !== -1) return;
            // Block non-digit keys
            if (e.key && !/^\d$/.test(e.key)) e.preventDefault();
        });
    }

    function updateSisaInfo() {
        var totalEl = document.getElementById('pay-total');
        if (!totalEl || !totalEl.dataset.raw) return;
        var total    = parseInt(totalEl.dataset.raw, 10) || 0;
        var cash     = parseInt(document.getElementById('pay-cash').value, 10) || 0;
        var transfer = parseInt(document.getElementById('pay-transfer').value, 10) || 0;
        var sisa     = total - cash - transfer;
        var infoEl   = document.getElementById('pay-sisa-info');
        if (!infoEl) return;
        if (total > 0) {
            if (sisa > 0) {
                infoEl.textContent = 'Sisa belum tercatat: Rp ' + formatRibuan(sisa);
                infoEl.className = 'text-warning small';
            } else if (sisa < 0) {
                infoEl.textContent = 'Melebihi total tagihan: Rp ' + formatRibuan(Math.abs(sisa));
                infoEl.className = 'text-danger small';
            } else {
                infoEl.textContent = 'Sesuai total tagihan ✓';
                infoEl.className = 'text-success small';
            }
        } else {
            infoEl.textContent = '';
        }
    }

    function fillCashFromTotal() {
        var totalEl = document.getElementById('pay-total');
        if (!totalEl) return;

        var total = parseInt(totalEl.dataset.raw, 10) || 0;
        if (total <= 0) return;

        var cashDisplay = document.getElementById('pay-cash-display');
        var cashHidden = document.getElementById('pay-cash');
        var transferDisplay = document.getElementById('pay-transfer-display');
        var transferHidden = document.getElementById('pay-transfer');

        cashDisplay.value = formatRibuan(total);
        cashHidden.value = total;
        transferDisplay.value = '';
        transferHidden.value = '';
        updateSisaInfo();
        cashDisplay.focus();
    }

    attachMoneyInput('pay-cash-display', 'pay-cash');
    attachMoneyInput('pay-transfer-display', 'pay-transfer');

    var payTotal = document.getElementById('pay-total');
    payTotal.addEventListener('click', fillCashFromTotal);
    payTotal.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        e.preventDefault();
        fillCashFromTotal();
    });

    var payForm = document.getElementById('form-pay');
    var paySubmitButton = document.getElementById('btn-pay-submit');

    payForm.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        if (e.target && e.target.tagName === 'TEXTAREA') return;
        if (paySubmitButton.disabled) return;

        e.preventDefault();
        if (typeof payForm.requestSubmit === 'function') {
            payForm.requestSubmit(paySubmitButton);
        } else {
            paySubmitButton.click();
        }
    });

    // Submit form bayar via AJAX
    payForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var form = this;
        var submitBtn = paySubmitButton;
        submitBtn.disabled = true;
        var origText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Memproses...';

        var formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: formData,
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = origText;
            $('#modal-pay').modal('hide');
            if (res.ok) {
                if (window.showToast) showToast(res.data.status || 'Invoice dibayar.', 'success');
                document.dispatchEvent(new CustomEvent('rafen:ajax-success'));
            } else {
                if (window.showToast) showToast((res.data && res.data.error) || 'Gagal memproses pembayaran.', 'danger');
            }
        })
        .catch(function () {
            submitBtn.disabled = false;
            submitBtn.innerHTML = origText;
            if (window.showToast) showToast('Terjadi kesalahan.', 'danger');
        });
    });
})();
</script>
@endsection
