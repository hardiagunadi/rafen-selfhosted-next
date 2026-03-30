@extends('layouts.admin')

@section('title', 'Payment Gateway')

@section('content')
<div class="container-fluid">
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h4 class="mb-0"><i class="fas fa-credit-card mr-2 text-primary"></i>Payment Gateway</h4>
            <small class="text-muted">Konfigurasi payment gateway untuk pembayaran langganan tenant</small>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalAddGateway">
                <i class="fas fa-plus mr-1"></i> Tambah Gateway
            </button>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Nama</th>
                            <th>Code</th>
                            <th>Provider</th>
                            <th>Merchant Code</th>
                            <th>API Key</th>
                            <th>Fee Platform</th>
                            <th>Mode</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($gateways as $gateway)
                        <tr>
                            <td>
                                <strong>{{ $gateway->name }}</strong>
                                @if($gateway->fee_description)
                                    <br><small class="text-muted">{{ $gateway->fee_description }}</small>
                                @endif
                            </td>
                            <td><code>{{ $gateway->code }}</code></td>
                            <td>
                                @php
                                    $providerLabels = ['tripay' => 'Tripay', 'duitku' => 'Duitku', 'midtrans' => 'Midtrans'];
                                    $providerColors = ['tripay' => 'info', 'duitku' => 'warning', 'midtrans' => 'primary'];
                                @endphp
                                <span class="badge badge-{{ $providerColors[$gateway->provider] ?? 'secondary' }}">
                                    {{ $providerLabels[$gateway->provider] ?? $gateway->provider }}
                                </span>
                            </td>
                            <td>{{ $gateway->merchant_code ?: '-' }}</td>
                            <td>
                                @if($gateway->api_key)
                                    <code>{{ substr($gateway->api_key, 0, 6) }}••••••••</code>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if((float)$gateway->platform_fee_percent > 0)
                                    <span class="badge badge-warning">{{ number_format($gateway->platform_fee_percent, 2) }}%</span>
                                @else
                                    <span class="text-muted">0%</span>
                                @endif
                            </td>
                            <td>
                                @if($gateway->is_sandbox)
                                    <span class="badge badge-warning">Sandbox</span>
                                @else
                                    <span class="badge badge-success">Production</span>
                                @endif
                            </td>
                            <td>
                                @if($gateway->is_active)
                                    <span class="badge badge-success">Aktif</span>
                                @else
                                    <span class="badge badge-secondary">Nonaktif</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-gateway"
                                    data-id="{{ $gateway->id }}"
                                    data-name="{{ $gateway->name }}"
                                    data-code="{{ $gateway->code }}"
                                    data-provider="{{ $gateway->provider }}"
                                    data-merchant-code="{{ $gateway->merchant_code }}"
                                    data-sandbox="{{ $gateway->is_sandbox ? '1' : '0' }}"
                                    data-active="{{ $gateway->is_active ? '1' : '0' }}"
                                    data-fee="{{ $gateway->platform_fee_percent }}"
                                    data-fee-desc="{{ $gateway->fee_description }}"
                                    data-toggle="modal" data-target="#modalEditGateway">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="fas fa-credit-card fa-2x mb-2 d-block"></i>
                                Belum ada payment gateway. Tambahkan gateway pertama.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Modal Tambah Gateway --}}
<div class="modal fade" id="modalAddGateway" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('super-admin.payment-gateways.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus mr-1"></i> Tambah Payment Gateway</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nama <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name') }}" placeholder="Tripay Production" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" id="add_code" class="form-control @error('code') is-invalid @enderror"
                                    value="{{ old('code') }}" placeholder="duitku_prod" required>
                                <small class="text-muted">Unique identifier, tidak bisa diubah setelah dibuat.</small>
                                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Provider <span class="text-danger">*</span></label>
                                <select name="provider" id="add_provider" class="form-control @error('provider') is-invalid @enderror" required>
                                    <option value="">-- Pilih Provider --</option>
                                    <option value="tripay" {{ old('provider') === 'tripay' ? 'selected' : '' }}>Tripay</option>
                                    <option value="duitku" {{ old('provider') === 'duitku' ? 'selected' : '' }}>Duitku</option>
                                    <option value="midtrans" {{ old('provider') === 'midtrans' ? 'selected' : '' }}>Midtrans</option>
                                </select>
                                @error('provider')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-6" id="add_merchant_code_wrap" style="display:none;">
                            <div class="form-group">
                                <label id="add_merchant_code_label">Merchant Code</label>
                                <input type="text" name="merchant_code" id="add_merchant_code" class="form-control"
                                    value="{{ old('merchant_code') }}" placeholder="">
                            </div>
                        </div>
                    </div>
                    <div class="row" id="add_keys_row" style="display:none;">
                        <div class="col-md-6" id="add_api_key_wrap">
                            <div class="form-group">
                                <label id="add_api_key_label">API Key</label>
                                <input type="password" name="api_key" class="form-control"
                                    placeholder="" autocomplete="new-password">
                            </div>
                        </div>
                        <div class="col-md-6" id="add_private_key_wrap">
                            <div class="form-group">
                                <label id="add_private_key_label">Private Key</label>
                                <input type="password" name="private_key" class="form-control"
                                    placeholder="" autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                    <div id="add_callback_info" class="alert alert-info py-2 px-3 small" style="display:none;"></div>
                    <hr>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Fee Platform (%)</label>
                                <div class="input-group">
                                    <input type="number" name="platform_fee_percent" class="form-control"
                                        value="{{ old('platform_fee_percent', 0) }}" min="0" max="100" step="0.01" placeholder="0.00">
                                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                                </div>
                                <small class="text-muted">Fee yang dipotong dari saldo tenant. 0 = tidak ada fee.</small>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Keterangan Fee</label>
                                <input type="text" name="fee_description" class="form-control"
                                    value="{{ old('fee_description') }}" placeholder="cth: Biaya MDR QRIS 0.7% + admin 0.3%">
                                <small class="text-muted">Ditampilkan ke tenant saat memilih platform gateway.</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Mode</label>
                                <div>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" id="add_mode_sandbox" name="is_sandbox" value="1" class="custom-control-input"
                                            {{ old('is_sandbox', '0') === '1' ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="add_mode_sandbox">Sandbox (Testing)</label>
                                    </div>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" id="add_mode_prod" name="is_sandbox" value="0" class="custom-control-input"
                                            {{ old('is_sandbox', '0') !== '1' ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="add_mode_prod">Production</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status</label>
                                <div>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" id="add_status_active" name="is_active" value="1" class="custom-control-input"
                                            {{ old('is_active', '1') !== '0' ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="add_status_active">Aktif</label>
                                    </div>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" id="add_status_inactive" name="is_active" value="0" class="custom-control-input"
                                            {{ old('is_active', '1') === '0' ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="add_status_inactive">Nonaktif</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Edit Gateway --}}
<div class="modal fade" id="modalEditGateway" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formEditGateway" action="">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit mr-1"></i> Edit Payment Gateway</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nama <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="edit_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Code</label>
                                <input type="text" id="edit_code" class="form-control" disabled>
                                <small class="text-muted">Code tidak dapat diubah.</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Provider</label>
                                <input type="text" id="edit_provider" class="form-control" disabled>
                            </div>
                        </div>
                        <div class="col-md-6" id="edit_merchant_code_wrap">
                            <div class="form-group">
                                <label id="edit_merchant_code_label">Merchant Code</label>
                                <input type="text" name="merchant_code" id="edit_merchant_code" class="form-control" placeholder="">
                            </div>
                        </div>
                    </div>
                    <div class="row" id="edit_keys_row">
                        <div class="col-md-6" id="edit_api_key_wrap">
                            <div class="form-group">
                                <label id="edit_api_key_label">API Key</label>
                                <input type="password" name="api_key" class="form-control"
                                    placeholder="Kosongkan jika tidak ingin mengubah" autocomplete="new-password">
                            </div>
                        </div>
                        <div class="col-md-6" id="edit_private_key_wrap">
                            <div class="form-group">
                                <label id="edit_private_key_label">Private Key</label>
                                <input type="password" name="private_key" class="form-control"
                                    placeholder="Kosongkan jika tidak ingin mengubah" autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                    <div id="edit_callback_info" class="alert alert-info py-2 px-3 small" style="display:none;"></div>
                    <hr>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Fee Platform (%)</label>
                                <div class="input-group">
                                    <input type="number" name="platform_fee_percent" id="edit_fee_percent" class="form-control"
                                        min="0" max="100" step="0.01" placeholder="0.00">
                                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                                </div>
                                <small class="text-muted">Fee yang dipotong dari saldo tenant. 0 = tidak ada fee.</small>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Keterangan Fee</label>
                                <input type="text" name="fee_description" id="edit_fee_desc" class="form-control"
                                    placeholder="cth: Biaya MDR QRIS 0.7% + admin 0.3%">
                                <small class="text-muted">Ditampilkan ke tenant saat memilih platform gateway.</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Mode</label>
                                <div>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" id="edit_mode_sandbox" name="is_sandbox" value="1" class="custom-control-input">
                                        <label class="custom-control-label" for="edit_mode_sandbox">Sandbox (Testing)</label>
                                    </div>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" id="edit_mode_prod" name="is_sandbox" value="0" class="custom-control-input">
                                        <label class="custom-control-label" for="edit_mode_prod">Production</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status</label>
                                <div>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" id="edit_status_active" name="is_active" value="1" class="custom-control-input">
                                        <label class="custom-control-label" for="edit_status_active">Aktif</label>
                                    </div>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" id="edit_status_inactive" name="is_active" value="0" class="custom-control-input">
                                        <label class="custom-control-label" for="edit_status_inactive">Nonaktif</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Konfigurasi field per provider
var providerConfig = {
    duitku: {
        code:            'duitku_prod',
        namePlaceholder: 'Duitku Production',
        merchantLabel:   'Merchant Code',
        merchantPlaceholder: 'DSxxxxx',
        showMerchant:    true,
        apiLabel:        'API Key',
        apiPlaceholder:  'API Key dari dashboard Duitku',
        showPrivateKey:  false,
        callback:        'Callback URL: <code>{{ url("/payment/callback/duitku") }}</code>',
    },
    tripay: {
        code:            'tripay_prod',
        namePlaceholder: 'Tripay Production',
        merchantLabel:   'Merchant Code',
        merchantPlaceholder: 'T-xxxxx',
        showMerchant:    true,
        apiLabel:        'API Key',
        apiPlaceholder:  'API Key dari dashboard Tripay',
        privateLabel:    'Private Key',
        privatePlaceholder: 'Private Key dari dashboard Tripay',
        showPrivateKey:  true,
        callback:        'Callback URL: <code>{{ url("/payment/callback") }}</code>',
    },
    midtrans: {
        code:            'midtrans_prod',
        namePlaceholder: 'Midtrans Production',
        merchantLabel:   'Merchant ID',
        merchantPlaceholder: 'G-xxxxx',
        showMerchant:    true,
        apiLabel:        'Client Key',
        apiPlaceholder:  'Client Key dari dashboard Midtrans',
        privateLabel:    'Server Key',
        privatePlaceholder: 'Server Key dari dashboard Midtrans',
        showPrivateKey:  true,
        callback:        'Callback URL (Notification): <code>{{ url("/payment/callback/midtrans") }}</code>',
    },
};

function applyProviderConfig(prov, prefix) {
    prefix = prefix || 'add';
    var cfg = providerConfig[prov];
    if (!cfg) {
        $('#' + prefix + '_merchant_code_wrap').hide();
        $('#' + prefix + '_keys_row').hide();
        $('#' + prefix + '_callback_info').hide();
        return;
    }

    // Auto-fill code (add modal only)
    if (prefix === 'add') {
        var codeInput = $('#add_code');
        var existing = codeInput.val();
        var isDefault = Object.values(providerConfig).some(function(d){ return d.code === existing; });
        if (!existing || isDefault) codeInput.val(cfg.code);

        // Name placeholder
        $('input[name="name"]', '#modalAddGateway').attr('placeholder', cfg.namePlaceholder);
    }

    // Merchant code
    if (cfg.showMerchant) {
        $('#' + prefix + '_merchant_code_wrap').show();
        $('#' + prefix + '_merchant_code_label').text(cfg.merchantLabel);
        $('#' + prefix + '_merchant_code').attr('placeholder', cfg.merchantPlaceholder);
    } else {
        $('#' + prefix + '_merchant_code_wrap').hide();
    }

    // Keys row
    $('#' + prefix + '_keys_row').show();
    $('#' + prefix + '_api_key_label').text(cfg.apiLabel);
    $('input[name="api_key"]', '#' + prefix + '_api_key_wrap').attr('placeholder', cfg.apiPlaceholder);

    if (cfg.showPrivateKey) {
        $('#' + prefix + '_private_key_wrap').show();
        $('#' + prefix + '_private_key_label').text(cfg.privateLabel);
        $('input[name="private_key"]', '#' + prefix + '_private_key_wrap').attr('placeholder', cfg.privatePlaceholder);
    } else {
        $('#' + prefix + '_private_key_wrap').hide().find('input').val('');
    }

    // Callback info
    $('#' + prefix + '_callback_info').html('<i class="fas fa-info-circle mr-1"></i>' + cfg.callback).show();
}

$('#add_provider').on('change', function () {
    applyProviderConfig($(this).val(), 'add');
});

// Reset saat modal dibuka
$('#modalAddGateway').on('show.bs.modal', function () {
    $('#add_merchant_code_wrap, #add_keys_row, #add_callback_info').hide();
    var prov = $('#add_provider').val();
    if (prov) applyProviderConfig(prov, 'add');
});

$(document).on('click', '.btn-edit-gateway', function () {
    const btn = $(this);
    const id = btn.data('id');

    const routeTemplate = "{{ route('super-admin.payment-gateways.update', ':id') }}";
    $('#formEditGateway').attr('action', routeTemplate.replace(':id', id));
    var prov = btn.data('provider');
    $('#edit_name').val(btn.data('name'));
    $('#edit_code').val(btn.data('code'));
    $('#edit_provider').val(prov);
    $('#edit_merchant_code').val(btn.data('merchant-code'));

    $('input[name="is_sandbox"][value="' + btn.data('sandbox') + '"]', '#modalEditGateway').prop('checked', true);
    $('input[name="is_active"][value="' + btn.data('active') + '"]', '#modalEditGateway').prop('checked', true);
    $('#edit_fee_percent').val(btn.data('fee') || 0);
    $('#edit_fee_desc').val(btn.data('fee-desc') || '');

    applyProviderConfig(prov, 'edit');
});

@if($errors->any() && old('_method') !== 'PUT')
    $('#modalAddGateway').modal('show');
@endif
</script>
@endpush
