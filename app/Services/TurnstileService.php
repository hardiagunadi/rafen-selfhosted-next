<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileService
{
    private string $secretKey;

    public function __construct()
    {
        $this->secretKey = config('services.turnstile.secret_key', '');
    }

    public function verify(string $token, ?string $ip = null): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        if (empty($this->secretKey)) {
            Log::warning('Turnstile secret key not configured.');

            return false;
        }

        if (empty($token)) {
            return false;
        }

        try {
            $payload = [
                'secret' => $this->secretKey,
                'response' => $token,
            ];

            if ($ip) {
                $payload['remoteip'] = $ip;
            }

            $response = Http::asForm()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', $payload);

            return $response->successful() && $response->json('success') === true;
        } catch (\Throwable $e) {
            Log::error('Turnstile verification failed: '.$e->getMessage());

            return false;
        }
    }
}
