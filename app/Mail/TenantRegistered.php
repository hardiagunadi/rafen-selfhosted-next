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

class TenantRegistered extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $tenant,
        public string $plainPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Selamat Datang di ' . config('app.name') . ' — Akun Anda Siap',
        );
    }

    public function content(): Content
    {
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get();

        $settings  = \App\Models\TenantSettings::where('user_id', $this->tenant->id)->first();
        $subdomain = $settings?->admin_subdomain;
        $loginUrl  = $subdomain
            ? 'https://' . $subdomain . '.' . config('app.main_domain', 'rafen.id') . '/login'
            : config('app.url') . '/login';

        return new Content(
            view: 'emails.tenant.registered',
            with: [
                'tenant'        => $this->tenant,
                'plainPassword' => $this->plainPassword,
                'plans'         => $plans,
                'loginUrl'      => $loginUrl,
            ],
        );
    }
}
