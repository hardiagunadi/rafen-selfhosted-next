@extends('layouts.admin')

@section('title', 'User PPP')

@section('content')
    <div class="card" style="overflow: visible;">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="overflow: visible;">
            <div class="btn-group">
                <div class="dropdown">
                    <button class="btn btn-success btn-sm dropdown-toggle" type="button" id="managementDropdown" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bars"></i> Manajemen Pelanggan
                    </button>
                    <div class="dropdown-menu dropdown-menu-left" aria-labelledby="managementDropdown" style="min-width: 260px;">
                        <a class="dropdown-item" href="{{ route('ppp-users.create') }}">Tambah Pelanggan</a>
                        <a class="dropdown-item" href="{{ route('ppp-users.index') }}">List Pelanggan</a>
                        @if(auth()->user() && (auth()->user()->isSuperAdmin() || in_array(auth()->user()->role, ['administrator', 'noc', 'it_support'])))
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-success" href="{{ route('wa-blast.index') }}">
                            <i class="fab fa-whatsapp"></i> Kirim Pesan Broadcast
                        </a>
                        @endif
                        <div class="dropdown-header text-danger text-uppercase">Aksi Checkbox (Massal)</div>
                        <a class="dropdown-item text-danger bulk-delete-action" href="#">Hapus Terpilih</a>
                    </div>
                </div>
            </div>
            <div class="mt-2 mt-sm-0">
                <h4 class="mb-0">User PPP</h4>
            </div>
        </div>

        <div class="card-body">
            <div class="row text-center mb-3">
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-info"><i class="fas fa-users fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Registrasi Bulan Ini</div>
                            <div class="h5 mb-0">{{ $stats['registrasi_bulan_ini'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-success"><i class="fas fa-recycle fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Renewal Bulan Ini</div>
                            <div class="h5 mb-0">{{ $stats['renewal_bulan_ini'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-warning"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Pelanggan Isolir</div>
                            <div class="h5 mb-0">{{ $stats['pelanggan_isolir'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-danger"><i class="fas fa-ban fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Akun Disable</div>
                            <div class="h5 mb-0">{{ $stats['akun_disable'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Search + Filter bar (diisi oleh DataTables initComplete) --}}
            <div id="ppp-filter-bar" class="d-flex flex-wrap align-items-center mb-2" style="gap:.5rem;">
            </div>

            <div class="table-responsive">
                <table id="ppp-users-table" class="table table-striped table-hover mb-0" style="width:100%;">
                    <thead class="thead-light">
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="select-all"></th>
                            <th>ID Pelanggan</th>
                            <th>Nama</th>
                            <th>Tipe Service</th>
                            <th>Paket Langganan</th>
                            <th>Diperpanjang</th>
                            <th>Jatuh Tempo</th>
                            <th>Renew / Bayar</th>
                            <th class="text-right">Aksi</th>
                            <th>Owner Data</th>
                            <th>Teknisi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <form id="bulk-delete-form" action="{{ route('ppp-users.bulk-destroy') }}" method="POST">
            @csrf
            @method('DELETE')
        </form>
    </div>

    <style>
    #ppp-filter-bar { row-gap: .5rem; }
    #ppp-users-table_filter input { width: 160px; }
    @media (max-width: 575.98px) {
        #ppp-filter-bar { flex-direction: column; align-items: flex-start !important; }
        #ppp-users-table_filter { width: 100%; }
        #ppp-users-table_filter input { width: 100%; }
    }
    </style>
    <script>
    (function () {
        var initialFilterIsolir = @json(request()->boolean('filter_isolir'));
        var initialFilterTagihan = @json(request()->boolean('filter_tagihan'));
        var initialFilterOnProcess = @json(request()->boolean('filter_on_process'));
        var initialSearchQuery = @json((string) request('search', ''));

        function isFilterEnabled(selector, fallback) {
            var $element = $(selector);

            if (! $element.length) {
                return fallback;
            }

            return $element.is(':checked');
        }

        function init() {
            if (!document.getElementById('ppp-users-table')) return;
            if ($.fn.DataTable.isDataTable('#ppp-users-table')) {
                $('#ppp-users-table').DataTable().destroy();
            }
            var table = $('#ppp-users-table').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                ajax: {
                    url: '{{ route('ppp-users.datatable') }}',
                    type: 'GET',
                    data: function (d) {
                        d.filter_isolir = isFilterEnabled('#filter-isolir', initialFilterIsolir) ? '1' : '';
                        d.filter_tagihan = isFilterEnabled('#filter-tagihan', initialFilterTagihan) ? '1' : '';
                        d.filter_on_process = isFilterEnabled('#filter-on-process', initialFilterOnProcess) ? '1' : '';
                    }
                },
                search: {
                    search: initialSearchQuery
                },
                columns: [
                    { data: 'checkbox',    orderable: false, searchable: false, width: '40px' },
                    { data: 'customer_id', orderable: true },
                    { data: 'nama',        orderable: false },
                    { data: 'tipe',        orderable: false },
                    { data: 'paket',       orderable: false },
                    { data: 'diperpanjang',orderable: true },
                    { data: 'jatuh_tempo', orderable: true },
                    { data: 'renew_print', orderable: false, searchable: false },
                    { data: 'aksi',        orderable: false, searchable: false, className: 'text-right' },
                    { data: 'owner',       orderable: false },
                    { data: 'teknisi',     orderable: false },
                ],
                language: {
                    search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                    infoEmpty: 'Tidak ada data', infoFiltered: '(disaring dari _MAX_ total data)',
                    zeroRecords: 'Tidak ada data yang cocok.', emptyTable: 'Belum ada user PPP.',
                    paginate: { first: 'Pertama', last: 'Terakhir', next: 'Selanjutnya', previous: 'Sebelumnya' },
                    processing: 'Memuat...',
                },
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                order: [[6, 'asc']],
                dom: '<"row align-items-center"<"col-sm-6"l><"col-sm-6 d-flex justify-content-end"f>>rt<"row align-items-center mt-2"<"col-sm-6"i><"col-sm-6 d-flex justify-content-end"p>>',
                drawCallback: function () {
                    $('[data-toggle="tooltip"]').tooltip();
                },
                initComplete: function () {
                    var $bar = $('#ppp-filter-bar');
                    // Pindahkan search input bawaan DT ke filter bar
                    var $dtFilter = $('#ppp-users-table_filter');
                    $dtFilter.find('label').css({'display':'flex','align-items':'center','gap':'.4rem','margin':'0'});
                    $dtFilter.css({'margin':'0'});
                    $bar.append($dtFilter);

                    // Tambahkan toggle filter
                    var $switches = $('<div class="d-flex flex-wrap align-items-center" style="gap:.6rem;">'
                        + '<div class="custom-control custom-switch mb-0">'
                        +   '<input type="checkbox" class="custom-control-input" id="filter-isolir">'
                        +   '<label class="custom-control-label text-warning font-weight-bold" for="filter-isolir">Isolir</label>'
                        + '</div>'
                        + '<div class="custom-control custom-switch mb-0">'
                        +   '<input type="checkbox" class="custom-control-input" id="filter-tagihan">'
                        +   '<label class="custom-control-label text-danger font-weight-bold" for="filter-tagihan">Jatuh Tempo</label>'
                        + '</div>'
                        + '<div class="custom-control custom-switch mb-0">'
                        +   '<input type="checkbox" class="custom-control-input" id="filter-on-process">'
                        +   '<label class="custom-control-label text-info font-weight-bold" for="filter-on-process">On Process</label>'
                        + '</div>'
                        + '</div>');
                    $bar.append($switches);

                    $('#filter-isolir').prop('checked', initialFilterIsolir);
                    $('#filter-tagihan').prop('checked', initialFilterTagihan);
                    $('#filter-on-process').prop('checked', initialFilterOnProcess);

                    $('#filter-isolir, #filter-tagihan, #filter-on-process').on('change', function () {
                        table.ajax.reload();
                    });
                },
            });

            document.addEventListener('rafen:ajax-success', function () {
                table.ajax.reload(null, false);
            });

            $('#select-all').on('change', function () {
                $('#ppp-users-table tbody input[type="checkbox"]').prop('checked', this.checked);
            });

            $('.bulk-delete-action').on('click', function (e) {
                e.preventDefault();
                var ids = $('#ppp-users-table tbody input[name="ids[]"]:checked').map(function () { return this.value; }).get();
                if (!ids.length) { alert('Pilih user terlebih dahulu.'); return; }
                if (!confirm('Hapus ' + ids.length + ' user terpilih?')) return;
                var form = document.getElementById('bulk-delete-form');
                ids.forEach(function (id) {
                    var inp = document.createElement('input');
                    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
                    form.appendChild(inp);
                });
                form.submit();
            });

            $(document).on('click', '.toggle-status-btn', function (e) {
                e.preventDefault();
                var $el = $(this);
                var url = $el.data('toggle-url');
                $.post(url, { _token: '{{ csrf_token() }}' }, function (res) {
                    var isEnable = res.status === 'enable';
                    $el.removeClass('badge-success badge-danger')
                       .addClass(isEnable ? 'badge-success' : 'badge-danger')
                       .attr('title', 'Klik untuk ' + (isEnable ? 'disable' : 'enable'));
                });
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

    function formatRibuan(n) {
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

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
            if ([8,9,27,46,37,38,39,40,35,36].indexOf(e.keyCode) !== -1) return;
            if ((e.ctrlKey || e.metaKey) && [65,67,86,88].indexOf(e.keyCode) !== -1) return;
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
                if (window.showToast) window.showToast(res.data.status || 'Invoice dibayar.', 'success');
                document.dispatchEvent(new CustomEvent('rafen:ajax-success'));
            } else {
                if (window.showToast) window.showToast((res.data && res.data.error) || 'Gagal memproses pembayaran.', 'danger');
            }
        })
        .catch(function () {
            submitBtn.disabled = false;
            submitBtn.innerHTML = origText;
            if (window.showToast) window.showToast('Terjadi kesalahan.', 'danger');
        });
    });
})();
</script>
@endsection
