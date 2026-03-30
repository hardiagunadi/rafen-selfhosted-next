<?php

namespace App\Mail;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantPaymentConfirmed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $tenant,
        public Subscription $subscription,
        public Payment $payment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pembayaran Berhasil — Langganan ' . ($this->subscription->plan?->name ?? 'Anda') . ' Aktif',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant.payment-confirmed',
            with: [
                'tenant'       => $this->tenant,
                'subscription' => $this->subscription,
                'payment'      => $this->payment,
                'dashboardUrl' => config('app.url') . '/dashboard',
            ],
        );
    }
}
