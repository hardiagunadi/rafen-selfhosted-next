@extends('layouts.admin')

@section('title', 'Upload Bukti Transfer')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pembayaran via Transfer Bank</h3>
                <div class="card-tools">
                    @if($settings->hasPaymentGateway())
                    <a href="{{ route('payments.create-for-invoice', $invoice) }}" class="btn btn-sm btn-default">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                    @else
                    <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-default">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body">

                {{-- Invoice Summary --}}
                <div class="row mb-3">
                    <div class="col-6">
                        <small class="text-muted d-block">No. Invoice</small>
                        <strong>{{ $invoice->invoice_number }}</strong>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Pelanggan</small>
                        <strong>{{ $invoice->customer_name }}</strong>
                    </div>
                </div>
                <div class="alert alert-info py-2 mb-3">
                    <strong>Total yang harus ditransfer:</strong>
                    <span class="float-right font-weight-bold">Rp {{ number_format($invoice->total, 0, ',', '.') }}</span>
                </div>

                {{-- Rekening Tujuan --}}
                <h6 class="mb-2">Transfer ke Rekening:</h6>
                <div class="row mb-3">
                    @foreach($bankAccounts as $bank)
                    <div class="col-md-6 mb-2">
                        <div class="card border {{ $bank->is_primary ? 'border-primary' : '' }}">
                            <div class="card-body py-2 px-3">
                                <strong>{{ $bank->bank_name }}</strong>
                                @if($bank->is_primary)
                                    <span class="badge badge-primary float-right">Utama</span>
                                @endif
                                <br>
                                <span class="font-weight-bold" style="font-size:1.05em">{{ $bank->account_number }}</span><br>
                                <small class="text-muted">a.n. {{ $bank->account_name }}</small>
                                @if($bank->branch)
                                    <br><small class="text-muted">{{ $bank->branch }}</small>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <hr>

                {{-- Form Upload Bukti --}}
                <form action="{{ route('payments.manual-confirmation', $invoice) }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="form-group">
                        <label>Rekening yang Digunakan untuk Transfer <span class="text-danger">*</span></label>
                        <select name="bank_account_id" class="form-control @error('bank_account_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Rekening Tujuan --</option>
                            @foreach($bankAccounts as $bank)
                            <option value="{{ $bank->id }}" {{ old('bank_account_id') == $bank->id ? 'selected' : '' }}>
                                {{ $bank->bank_name }} — {{ $bank->account_number }} (a.n. {{ $bank->account_name }})
                            </option>
                            @endforeach
                        </select>
                        @error('bank_account_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label>Jumlah yang Ditransfer (Rp) <span class="text-danger">*</span></label>
                        <input type="number" name="amount_transferred"
                            class="form-control @error('amount_transferred') is-invalid @enderror"
                            value="{{ old('amount_transferred', $invoice->total) }}" min="1" required>
                        @error('amount_transferred')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label>Tanggal Transfer <span class="text-danger">*</span></label>
                        <input type="date" name="transfer_date"
                            class="form-control @error('transfer_date') is-invalid @enderror"
                            value="{{ old('transfer_date', now()->format('Y-m-d')) }}"
                            max="{{ now()->format('Y-m-d') }}" required>
                        @error('transfer_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label>Bukti Transfer (Foto/Screenshot) <span class="text-danger">*</span></label>
                        <div class="custom-file">
                            <input type="file" name="payment_proof" id="payment_proof"
                                class="custom-file-input @error('payment_proof') is-invalid @enderror"
                                accept="image/*" required onchange="previewProof(this)">
                            <label class="custom-file-label" for="payment_proof">Pilih file gambar...</label>
                        </div>
                        @error('payment_proof')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Format: JPG, PNG, GIF. Maks 5MB.</small>
                        <div id="proof-preview" class="mt-2" style="display:none;">
                            <img id="proof-img" src="" alt="Preview" class="img-fluid rounded border" style="max-height:200px;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Catatan (opsional)</label>
                        <textarea name="notes" class="form-control @error('notes') is-invalid @enderror"
                            rows="2" placeholder="Contoh: transfer dari BCA atas nama Budi">{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="fas fa-info-circle mr-1"></i>
                        Setelah upload, admin akan memverifikasi bukti transfer dalam <strong>1x24 jam</strong>.
                        Status invoice akan diperbarui setelah konfirmasi.
                    </div>

                    <button type="submit" class="btn btn-success btn-block">
                        <i class="fas fa-paper-plane mr-1"></i> Kirim Bukti Transfer
                    </button>
                </form>

            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
function previewProof(input) {
    var preview = document.getElementById('proof-preview');
    var img     = document.getElementById('proof-img');
    var label   = input.nextElementSibling;
    if (input.files && input.files[0]) {
        label.textContent = input.files[0].name;
        var reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endpush
