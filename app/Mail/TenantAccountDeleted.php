<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantAccountDeleted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $tenantName,
        public string $tenantEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Akun Trial Anda Telah Dihapus — ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant.account-deleted',
            with: [
                'tenantName' => $this->tenantName,
                'tenantEmail' => $this->tenantEmail,
                'registerUrl' => config('app.url') . '/register',
                'contactUrl'  => config('app.url'),
            ],
        );
    }
}
