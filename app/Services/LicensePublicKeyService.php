<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class LicensePublicKeyService
{
    public function isEditable(): bool
    {
        return (bool) config('license.public_key_editable', false);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSnapshot(): array
    {
        $publicKey = (string) config('license.public_key');

        return [
            'public_key' => $publicKey,
            'has_public_key' => $publicKey !== '',
            'is_editable' => $this->isEditable(),
        ];
    }

    public function store(string $publicKey): void
    {
        $normalizedPublicKey = trim($publicKey);

        $this->writeEnvironmentValue('LICENSE_PUBLIC_KEY', $normalizedPublicKey);

        config()->set('license.public_key', $normalizedPublicKey);
    }

    private function writeEnvironmentValue(string $key, string $value): void
    {
        $environmentFilePath = app()->environmentFilePath();

        if (! File::exists($environmentFilePath)) {
            File::put($environmentFilePath, '');
        }

        $environmentContents = (string) File::get($environmentFilePath);
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
        $line = $key.'='.$value;

        if (preg_match($pattern, $environmentContents)) {
            $environmentContents = (string) preg_replace($pattern, $line, $environmentContents);
        } else {
            $environmentContents = rtrim($environmentContents).PHP_EOL.$line.PHP_EOL;
        }

        File::put($environmentFilePath, $environmentContents);
    }
}
