<?php

namespace App\Services;

class LicenseSignatureService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload): bool
    {
        $signature = $payload['signature'] ?? null;
        $publicKey = (string) config('license.public_key');

        if (! is_string($signature) || $signature === '' || $publicKey === '') {
            return false;
        }

        if (! extension_loaded('sodium')) {
            return false;
        }

        $decodedSignature = base64_decode($signature, true);
        $decodedPublicKey = base64_decode($publicKey, true);

        if ($decodedSignature === false || $decodedPublicKey === false) {
            return false;
        }

        return sodium_crypto_sign_verify_detached(
            $decodedSignature,
            $this->canonicalize($this->signablePayload($payload)),
            $decodedPublicKey,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function signablePayload(array $payload): array
    {
        unset($payload['signature']);

        return $this->sortRecursively($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function canonicalize(array $payload): string
    {
        return json_encode(
            $this->sortRecursively($payload),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    private function sortRecursively(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortRecursively($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursively($item);
        }

        return $value;
    }
}
