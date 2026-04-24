@extends('layouts.admin')

@section('title', 'Manajemen Voucher')

@section('content')
    <div class="card" style="overflow: visible;">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="overflow: visible;">
            <div class="btn-group">
                <div class="dropdown">
                    <button class="btn btn-success btn-sm dropdown-toggle" type="button" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bars"></i> Manajemen Voucher
                    </button>
                    <div class="dropdown-menu dropdown-menu-left" style="min-width: 200px;">
                        <a class="dropdown-item" href="{{ route('vouchers.create') }}">Generate Voucher Baru</a>
                        <a class="dropdown-item" href="{{ route('vouchers.index') }}">List Semua Voucher</a>
                        <div class="dropdown-header text-danger text-uppercase">Aksi Massal</div>
                        <a class="dropdown-item text-danger bulk-delete-action" href="#">Hapus Unused Terpilih</a>
                    </div>
                </div>
            </div>
            <div class="mt-2 mt-sm-0">
                <h4 class="mb-0">Voucher Hotspot</h4>
            </div>
        </div>

        <div class="card-body">
            <div class="row text-center mb-3">
                <div class="col-md-4 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-success"><i class="fas fa-ticket-alt fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Unused</div>
                            <div class="h5 mb-0">{{ number_format($stats['unused']) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-info"><i class="fas fa-check-circle fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Used</div>
                            <div class="h5 mb-0">{{ number_format($stats['used']) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-secondary"><i class="fas fa-times-circle fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Expired</div>
                            <div class="h5 mb-0">{{ number_format($stats['expired']) }}</div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="table-responsive">
                <table id="vouchers-table" class="table table-striped table-hover mb-0" style="width:100%;">
                    <thead class="thead-light">
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="select-all"></th>
                            <th>Kode</th>
                            <th>Batch</th>
                            <th>Profil Hotspot</th>
                            <th>Status</th>
                            <th>Expired</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <form id="bulk-delete-form" action="{{ route('vouchers.bulk-destroy') }}" method="POST">
            @csrf
            @method('DELETE')
        </form>
    </div>

    <div class="modal fade" id="sendVoucherWaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fab fa-whatsapp text-success mr-1"></i> Kirim Voucher ke WhatsApp</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="send-voucher-wa-form">
                    <div class="modal-body">
                        <div class="alert alert-light border">
                            <div><strong>Kode:</strong> <span id="voucher-wa-code">-</span></div>
                            <div><strong>Profil:</strong> <span id="voucher-wa-profile">-</span></div>
                        </div>
                        <div class="form-group">
                            <label for="voucher-wa-provider">Provider WhatsApp</label>
                            <select id="voucher-wa-provider" name="provider" class="form-control"></select>
                            <small id="voucher-wa-provider-hint" class="text-muted d-block mt-1"></small>
                        </div>
                        <div class="form-group mb-0">
                            <label for="voucher-wa-phone">Nomor WhatsApp Tujuan</label>
                            <input type="text" id="voucher-wa-phone" name="phone" class="form-control" placeholder="Contoh: 081234567890" required>
                            <small class="text-muted">Nomor akan dinormalisasi otomatis ke format WhatsApp Indonesia.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success" id="send-voucher-wa-submit">
                            <i class="fab fa-whatsapp"></i> Kirim
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var dtTable = null;
        var activeSendWaUrl = '';
        var activeProviderOptions = [];

        function setVoucherProviderOptions(options, defaultProvider) {
            var select = document.getElementById('voucher-wa-provider');
            var hint = document.getElementById('voucher-wa-provider-hint');

            if (!select) return;

            select.innerHTML = '';
            activeProviderOptions = Array.isArray(options) ? options : [];

            activeProviderOptions.forEach(function (option) {
                var el = document.createElement('option');
                el.value = option.value || '';
                el.textContent = option.label || option.value || 'Provider';
                if ((option.value || '') === defaultProvider) {
                    el.selected = true;
                }
                select.appendChild(el);
            });

            if (!select.value && activeProviderOptions.length > 0) {
                select.value = activeProviderOptions[0].value || '';
            }

            function renderHint() {
                var current = activeProviderOptions.find(function (option) {
                    return option.value === select.value;
                });

                hint.textContent = current && current.hint ? current.hint : '';
            }

            select.onchange = renderHint;
            renderHint();
        }

        function init() {
            if (!document.getElementById('vouchers-table')) return;
            if (dtTable) { dtTable.destroy(); dtTable = null; }

            dtTable = $('#vouchers-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("vouchers.datatable") }}',
                    type: 'GET',
                    data: function (d) { },
                },
                columns: [
                    { data: 'checkbox', orderable: false, searchable: false, width: '40px' },
                    { data: 'code',     orderable: true },
                    { data: 'batch',    orderable: true },
                    { data: 'profil',   orderable: false },
                    { data: 'status',   orderable: false },
                    { data: 'expired',  orderable: true },
                    { data: 'aksi',     orderable: false, searchable: false, className: 'text-right' },
                ],
                language: {
                    search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                    infoEmpty: 'Tidak ada data', infoFiltered: '(disaring dari _MAX_ total data)',
                    zeroRecords: 'Tidak ada voucher yang cocok.', emptyTable: 'Belum ada voucher.',
                    paginate: { first: 'Pertama', last: 'Terakhir', next: 'Selanjutnya', previous: 'Sebelumnya' },
                    processing: 'Memuat...',
                },
                pageLength: 20,
                lengthMenu: [[20, 50, 100, 200], [20, 50, 100, 200]],
                order: [[1, 'asc']],
                stateSave: false,
                drawCallback: function () { },
            });


            $('#select-all').off('change.voucher').on('change.voucher', function () {
                $('#vouchers-table tbody input[type="checkbox"]:not(:disabled)').prop('checked', this.checked);
            });

            $('.bulk-delete-action').off('click.voucher').on('click.voucher', function (e) {
                e.preventDefault();
                var ids = $('#vouchers-table tbody input[name="ids[]"]:checked').map(function () { return this.value; }).get();
                if (!ids.length) { alert('Pilih voucher unused terlebih dahulu.'); return; }
                if (!confirm('Hapus ' + ids.length + ' voucher terpilih?')) return;
                var form = document.getElementById('bulk-delete-form');
                ids.forEach(function (id) {
                    var inp = document.createElement('input');
                    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
                    form.appendChild(inp);
                });
                form.submit();
            });

            $(document).off('click.voucherWa', '.btn-send-voucher-wa').on('click.voucherWa', '.btn-send-voucher-wa', function () {
                activeSendWaUrl = this.dataset.sendWaUrl || '';
                var providerOptions = [];
                try {
                    providerOptions = JSON.parse(this.dataset.providerOptions || '[]');
                } catch (error) {
                    providerOptions = [];
                }

                document.getElementById('voucher-wa-code').textContent = this.dataset.voucherCode || '-';
                document.getElementById('voucher-wa-profile').textContent = this.dataset.voucherProfile || '-';
                document.getElementById('voucher-wa-phone').value = '';
                setVoucherProviderOptions(providerOptions, this.dataset.defaultProvider || '');
                $('#sendVoucherWaModal').modal('show');
            });

            $('#send-voucher-wa-form').off('submit.voucherWa').on('submit.voucherWa', function (e) {
                e.preventDefault();

                var phoneInput = document.getElementById('voucher-wa-phone');
                var submitButton = document.getElementById('send-voucher-wa-submit');
                var originalHtml = submitButton.innerHTML;
                var phone = (phoneInput.value || '').trim();
                var provider = (document.getElementById('voucher-wa-provider').value || '').trim();

                if (!activeSendWaUrl || !phone) {
                    window.AppAjax.showToast('Nomor WhatsApp tujuan wajib diisi.', 'warning');
                    return;
                }

                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';

                fetch(activeSendWaUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ phone: phone, provider: provider }),
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.status) {
                        $('#sendVoucherWaModal').modal('hide');
                        window.AppAjax.showToast(data.status, 'success');
                        return;
                    }

                    window.AppAjax.showToast(data.error || 'Gagal mengirim voucher ke WhatsApp.', 'danger');
                })
                .catch(function () {
                    window.AppAjax.showToast('Terjadi kesalahan saat mengirim voucher.', 'danger');
                })
                .finally(function () {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalHtml;
                });
            });
        }
        document.addEventListener('DOMContentLoaded', init);
        if (document.readyState !== 'loading') init();
    })();
    </script>
@endsection
