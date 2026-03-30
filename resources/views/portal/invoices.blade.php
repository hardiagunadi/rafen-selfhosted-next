@extends('portal.layout')

@section('title', 'Riwayat Tagihan')

@php $tenantSettings = $pppUser->owner?->tenantSettings; @endphp

@section('content')
<h5 class="mb-3"><i class="fas fa-file-invoice"></i> Riwayat Tagihan</h5>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="thead-dark">
                    <tr>
                        <th>No. Invoice</th>
                        <th>Paket</th>
                        <th>Total</th>
                        <th>Jatuh Tempo</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                    @php
                        $isPaid = $invoice->status === 'lunas' || $invoice->status === 'sudah_bayar' || $invoice->paid_at;
                        $isOverdue = !$isPaid && $invoice->due_date && $invoice->due_date->isPast();
                        $currentDueDate = $pppUser->jatuh_tempo?->copy()->endOfDay();
                        $isHistoricalUnpaid = $invoice->isHistoricalUnpaid($currentDueDate);
                        $isCurrentBillingInvoice = $invoice->isCurrentBillingInvoice($currentDueDate);
                    @endphp
                    <tr>
                        <td>
                            <small>{{ $invoice->invoice_number }}</small>
                            @if($isHistoricalUnpaid)
                            <div class="mt-1"><span class="badge badge-secondary">Invoice Tunggakan</span></div>
                            @elseif($isCurrentBillingInvoice)
                            <div class="mt-1"><span class="badge badge-info">Perpanjangan Bulan Berjalan</span></div>
                            @endif
                        </td>
                        <td><small>{{ $invoice->paket_langganan ?? '-' }}</small></td>
                        <td><strong>Rp {{ number_format($invoice->total, 0, ',', '.') }}</strong></td>
                        <td><small>{{ $invoice->due_date?->format('d/m/Y') }}</small></td>
                        <td>
                            @if($isPaid)
                            <span class="badge badge-success">LUNAS</span>
                            @elseif($isOverdue)
                            <span class="badge badge-danger">OVERDUE</span>
                            @else
                            <span class="badge badge-warning">BELUM BAYAR</span>
                            @endif
                        </td>
                        <td>
                            @if(!$isPaid && $invoice->payment_token)
                            <a href="{{ route('customer.invoice', $invoice->payment_token) }}" class="btn btn-xs btn-primary" target="_blank">
                                <i class="fas fa-credit-card"></i> Bayar
                            </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">Belum ada tagihan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($invoices->hasPages())
    <div class="card-footer">
        {{ $invoices->links() }}
    </div>
    @endif
</div>
@endsection
