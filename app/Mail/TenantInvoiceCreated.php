<?php

namespace App\Mail;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantInvoiceCreated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $tenant,
        public Subscription $subscription,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tagihan Langganan ' . ($this->subscription->plan?->name ?? '') . ' — ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        $token = $this->subscription->getOrCreatePaymentToken();
        $paymentUrl = route('subscription.payment.public', $token);

        return new Content(
            view: 'emails.tenant.invoice-created',
            with: [
                'tenant'       => $this->tenant,
                'subscription' => $this->subscription,
                'paymentUrl'   => $paymentUrl,
            ],
        );
    }
}
