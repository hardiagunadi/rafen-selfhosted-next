<?php

use Illuminate\Support\Facades\File;

afterEach(function (): void {
    File::delete(storage_path('framework/self-hosted-update-notice-test.json'));
});

it('publishes self hosted update notice metadata to a target file', function () {
    config()->set('app.version', '2026.03.30-main.10');

    $target = storage_path('framework/self-hosted-update-notice-test.json');

    $this->artisan("self-hosted:publish-update-notice {$target}")
        ->expectsOutputToContain('Metadata update self-hosted berhasil dipublikasikan.')
        ->expectsOutputToContain('Available Version : 2026.03.30-main.10')
        ->assertSuccessful();

    expect(File::exists($target))->toBeTrue();

    $payload = json_decode((string) File::get($target), true);

    expect($payload)
        ->toBeArray()
        ->and($payload['available_version'] ?? null)->toBe('2026.03.30-main.10')
        ->and($payload['manual_only'] ?? null)->toBeTrue();
});

it('prints json payload when requested', function () {
    config()->set('app.version', '2026.03.30-main.11');

    $target = storage_path('framework/self-hosted-update-notice-test.json');

    $this->artisan("self-hosted:publish-update-notice {$target} --json")
        ->expectsOutputToContain('"available_version": "2026.03.30-main.11"')
        ->assertSuccessful();
});
