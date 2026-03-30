@extends('layouts.admin')

@section('title', 'Bayar Invoice')

@section('content')
<style>
.mf-page { display:flex;flex-direction:column;gap:1.1rem; }
.mf-page-header { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem; }
.mf-page-header-left { display:flex;align-items:center;gap:.85rem; }
.mf-page-icon { width:46px;height:46px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;color:#fff;flex-shrink:0;box-shadow:0 4px 14px rgba(0,0,0,.15); }
.mf-page-title { font-size:1.15rem;font-weight:700;color:var(--app-text,#0f172a);line-height:1.2; }
.mf-dim { color:var(--app-text-soft,#5b6b83);font-weight:500; }
.mf-page-sub { font-size:.8rem;color:var(--app-text-soft,#5b6b83);margin-top:.15rem; }
.mf-header-actions { display:flex;gap:.5rem;align-items:center;flex-wrap:wrap; }
.mf-btn-back { display:inline-flex;align-items:center;padding:.4rem .95rem;border-radius:9px;border:1px solid var(--app-border,#d7e1ee);background:#fff;color:var(--app-text,#0f172a);font-size:.82rem;font-weight:600;text-decoration:none;transition:background 140ms,transform 140ms; }
.mf-btn-back:hover { background:#f1f5ff;transform:translateY(-1px);color:var(--app-text,#0f172a);text-decoration:none; }
.mf-grid { display:flex;flex-direction:column;gap:1rem; }
.mf-section { background:var(--app-surface,#fff);border:1px solid var(--app-border,#d7e1ee);border-radius:16px;box-shadow:0 4px 16px rgba(15,23,42,.05);overflow:hidden; }
.mf-section-header { display:flex;align-items:center;gap:.75rem;padding:.8rem 1.25rem;background:#f8fbff;border-bottom:1px solid var(--app-border,#d7e1ee); }
.mf-section-icon { width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.9rem;color:#fff;flex-shrink:0; }
.mf-section-title { font-size:.9rem;font-weight:700;color:var(--app-text,#0f172a); }
.mf-section-body { padding:1.1rem 1.25rem;display:flex;flex-direction:column;gap:.85rem; }
.mf-row { display:grid;grid-template-columns:repeat(2,1fr);gap:.85rem; }
@media (max-width:767px) { .mf-row { grid-template-columns:1fr; } }
.mf-field { display:flex;flex-direction:column;gap:.3rem; }
.mf-label { font-size:.77rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--app-text-soft,#5b6b83);display:flex;align-items:center;gap:.4rem; }
.mf-hint { font-size:.73rem;color:var(--app-text-soft,#5b6b83); }
.mf-input { height:38px;border-radius:9px;border:1px solid var(--app-border,#d7e1ee);padding:0 .75rem;font-size:.85rem;color:var(--app-text,#0f172a);background:#fff;outline:none;width:100%;transition:border-color 150ms,box-shadow 150ms; }
.mf-footer { display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;padding:1rem 1.25rem;background:var(--app-surface,#fff);border:1px solid var(--app-border,#d7e1ee);border-radius:14px; }
.mf-btn-cancel { font-size:.84rem;font-weight:600;color:var(--app-text-soft,#5b6b83);text-decoration:none;padding:.4rem .75rem;transition:color 140ms; }
.mf-btn-cancel:hover { color:var(--app-text,#0f172a);text-decoration:none; }
.mf-btn-submit { display:inline-flex;align-items:center;height:38px;padding:0 1.4rem;border-radius:10px;border:none;background:linear-gradient(140deg,#0369a1,#0ea5e9);color:#fff;font-size:.88rem;font-weight:700;cursor:pointer;box-shadow:0 3px 12px rgba(14,165,233,.25);transition:opacity 140ms,transform 140ms; }
.mf-btn-submit:hover { opacity:.9;transform:translateY(-1px); }
.mf-btn-submit:disabled { opacity:.45;cursor:not-allowed;transform:none; }

/* Invoice detail table */
.mf-invoice-table { width:100%;border-collapse:collapse;font-size:.84rem; }
.mf-invoice-table td { padding:.4rem .25rem;color:var(--app-text,#0f172a); }
.mf-invoice-table td:first-child { color:var(--app-text-soft,#5b6b83);width:40%; }

/* Total amount box */
.mf-total-box { background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #bbf7d0;border-radius:12px;padding:1.1rem 1.25rem;text-align:center; }
.mf-total-label { font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#15803d;margin-bottom:.35rem; }
.mf-total-amount { font-size:1.6rem;font-weight:800;color:#15803d;line-height:1; }

/* Payment channel grid */
.mf-channel-group-label { font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--app-text-soft,#5b6b83);margin-bottom:.4rem; }
.mf-channel-group-desc { font-size:.76rem;color:var(--app-text-soft,#5b6b83);margin-bottom:.65rem; }
.mf-channel-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:.5rem;margin-bottom:.85rem; }
.payment-channel-card { border:2px solid var(--app-border,#d7e1ee);border-radius:10px;padding:.6rem .5rem;text-align:center;cursor:pointer;transition:border-color 160ms,background 160ms;background:#fff; }
.payment-channel-card:hover { border-color:#10b981; }
.payment-channel-card.selected { border-color:#10b981;background:#f0fdf4; }
.payment-channel-card p { font-size:.72rem;margin:0;color:var(--app-text,#0f172a);line-height:1.3; }

/* Bank account cards */
.mf-bank-card { border:1px solid var(--app-border,#d7e1ee);border-radius:10px;padding:.65rem .9rem;font-size:.84rem;background:#fff; }
.mf-bank-card strong { color:var(--app-text,#0f172a); }
.mf-bank-card .mf-bank-number { font-weight:700;font-size:.9rem;color:var(--app-text,#0f172a); }
.mf-bank-card small { color:var(--app-text-soft,#5b6b83); }
.mf-badge-primary { display:inline-block;font-size:.68rem;font-weight:700;padding:.1rem .45rem;border-radius:20px;background:#dbeafe;color:#1d4ed8;margin-left:.3rem; }

/* Manual transfer button */
.mf-btn-manual { display:flex;align-items:center;justify-content:center;gap:.5rem;width:100%;height:40px;border-radius:10px;border:1.5px solid #10b981;background:#fff;color:#065f46;font-size:.86rem;font-weight:700;text-decoration:none;transition:background 140ms,color 140ms; }
.mf-btn-manual:hover { background:#f0fdf4;color:#065f46;text-decoration:none; }
</style>

<div class="mf-page">

    {{-- Page Header --}}
    <div class="mf-page-header">
        <div class="mf-page-header-left">
            <div class="mf-page-icon" style="background:linear-gradient(140deg,#065f46,#10b981);">
                <i class="fas fa-money-bill"></i>
            </div>
            <div>
                <div class="mf-page-title">Pembayaran Invoice</div>
                <div class="mf-page-sub"><span class="mf-dim">#{{ $invoice->invoice_number }}</span></div>
            </div>
        </div>
    </div>

    <div class="mf-grid">

        {{-- Detail Invoice --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#065f46,#10b981);">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <span class="mf-section-title">Detail Invoice</span>
            </div>
            <div class="mf-section-body">
                <div class="mf-row">
                    <div>
                        <table class="mf-invoice-table">
                            <tr>
                                <td>No. Invoice</td>
                                <td><strong>{{ $invoice->invoice_number }}</strong></td>
                            </tr>
                            <tr>
                                <td>Pelanggan</td>
                                <td>{{ $invoice->customer_name }}</td>
                            </tr>
                            <tr>
                                <td>Paket</td>
                                <td>{{ $invoice->paket_langganan }}</td>
                            </tr>
                            <tr>
                                <td>Jatuh Tempo</td>
                                <td>{{ $invoice->due_date?->format('d M Y') ?? '-' }}</td>
                            </tr>
                        </table>
                    </div>
                    <div>
                        <div class="mf-total-box">
                            <div class="mf-total-label">Total Pembayaran</div>
                            <div class="mf-total-amount">Rp {{ number_format($invoice->total, 0, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pilih Metode Pembayaran --}}
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#065f46,#10b981);">
                    <i class="fas fa-credit-card"></i>
                </div>
                <span class="mf-section-title">Pilih Metode Pembayaran</span>
            </div>
            <div class="mf-section-body">
                <form action="{{ route('payments.store-for-invoice', $invoice) }}" method="POST">
                    @csrf

                    @foreach($groupedChannels as $groupKey => $group)
                        <div class="mf-channel-group-label">{{ $group['name'] }}</div>
                        <div class="mf-channel-group-desc">{{ $group['description'] }}</div>
                        <div class="mf-channel-grid">
                            @foreach($group['channels'] as $channel)
                            <div class="payment-channel-card" onclick="selectChannel('{{ $channel['code'] }}')">
                                <input type="radio" name="payment_channel" value="{{ $channel['code'] }}" id="channel_{{ $channel['code'] }}" class="d-none">
                                @if(isset($channel['icon_url']))
                                <img src="{{ $channel['icon_url'] }}" alt="{{ $channel['name'] }}" style="height:30px;max-width:100%;margin-bottom:.3rem;">
                                @else
                                <i class="fas fa-credit-card fa-2x text-primary" style="margin-bottom:.3rem;"></i>
                                @endif
                                <p>{{ $channel['name'] }}</p>
                            </div>
                            @endforeach
                        </div>
                    @endforeach

                    <div style="text-align:center;margin-top:.5rem;">
                        <button type="submit" class="mf-btn-submit" id="payBtn" disabled style="height:44px;padding:0 2rem;font-size:.92rem;">
                            <i class="fas fa-lock" style="margin-right:.5rem;"></i> Bayar Sekarang
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Transfer Bank Manual --}}
        @if($manualEnabled && $bankAccounts->count() > 0)
        <div class="mf-section">
            <div class="mf-section-header">
                <div class="mf-section-icon" style="background:linear-gradient(140deg,#065f46,#10b981);">
                    <i class="fas fa-university"></i>
                </div>
                <span class="mf-section-title">Transfer Bank Manual</span>
            </div>
            <div class="mf-section-body">
                <p style="font-size:.83rem;color:var(--app-text-soft,#5b6b83);margin:0;">Upload bukti transfer setelah melakukan pembayaran ke rekening berikut. Admin akan mengkonfirmasi dalam 1x24 jam.</p>
                <div class="mf-row">
                    @foreach($bankAccounts as $bank)
                    <div class="mf-bank-card">
                        <strong>{{ $bank->bank_name }}</strong>
                        @if($bank->is_primary) <span class="mf-badge-primary">Utama</span> @endif
                        <br>
                        <span class="mf-bank-number">{{ $bank->account_number }}</span><br>
                        <small>a.n. {{ $bank->account_name }}</small>
                    </div>
                    @endforeach
                </div>
                <a href="{{ route('payments.manual-form', $invoice) }}" class="mf-btn-manual">
                    <i class="fas fa-university"></i> Upload Bukti Transfer Bank
                </a>
            </div>
        </div>
        @endif

    </div>

</div>

@push('scripts')
<script>
function selectChannel(code) {
    document.querySelectorAll('.payment-channel-card').forEach(card => card.classList.remove('selected'));
    document.getElementById('channel_' + code).checked = true;
    document.getElementById('channel_' + code).closest('.payment-channel-card').classList.add('selected');
    document.getElementById('payBtn').disabled = false;
}
</script>
@endpush
@endsection
