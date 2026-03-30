<?php

namespace App\Mail;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantTrialExpiringSoon extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $tenant,
        public int $daysLeft,
    ) {}

    public function envelope(): Envelope
    {
        $suffix = $this->daysLeft <= 0 ? 'Berakhir Hari Ini' : "Berakhir dalam {$this->daysLeft} Hari";

        return new Envelope(
            subject: 'Trial Anda ' . $suffix . ' — Pilih Paket untuk Melanjutkan',
        );
    }

    public function content(): Content
    {
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get();

        return new Content(
            view: 'emails.tenant.trial-expiring',
            with: [
                'tenant' => $this->tenant,
                'daysLeft' => $this->daysLeft,
                'plans' => $plans,
                'renewUrl' => config('app.url') . '/subscription/renew',
            ],
        );
    }
}
