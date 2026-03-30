<?php

use App\Models\TenantSettings;
use App\Models\User;
use App\Models\WaMultiSessionDevice;
use App\Services\WaGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('requires device token in tenant wa configuration', function () {
    config()->set('wa.multi_session.public_url', '');
    config()->set('wa.multi_session.auth_token', '');

    $user = User::factory()->create();
    $settings = TenantSettings::getOrCreate($user->id);

    $settings->update([
        'wa_gateway_url' => 'https://gateway.example/wa',
        'wa_gateway_token' => '   ',
        'wa_gateway_key' => 'master-key',
    ]);

    expect($settings->fresh()->hasWaConfigured())->toBeFalse()
        ->and(WaGatewayService::forTenant($settings->fresh()))->toBeNull();
});

it('fails fast when sending without device token', function () {
    config()->set('wa.multi_session.public_url', '');
    config()->set('wa.multi_session.auth_token', '');

    Log::spy();
    Http::fake();

    $service = new WaGatewayService('https://gateway.example/wa', '', 'master-key');
    $sent = $service->sendMessage('081234567890', 'Tes tanpa token', ['event' => 'blast']);

    expect($sent)->toBeFalse();

    Http::assertNothingSent();
    $this->assertDatabaseHas('wa_blast_logs', [
        'event' => 'blast',
        'status' => 'failed',
        'phone' => '081234567890',
        'reason' => 'Token perangkat WA belum diisi.',
    ]);
});

it('marks gateway message status failed as failed log', function () {
    config()->set('wa.multi_session.public_url', 'https://gateway.example/wa');
    config()->set('wa.multi_session.auth_token', 'device-token-001');
    config()->set('wa.multi_session.master_key', 'master-key');

    Log::spy();
    Http::fake([
        'https://gateway.example/wa/api/v2/send-message' => Http::response([
            'status' => true,
            'message' => 'Message processed',
            'data' => [
                'messages' => [
                    [
                        'status' => 'failed',
                        'ref_id' => 'ref-failed-001',
                    ],
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $settings = TenantSettings::getOrCreate($user->id);
    $settings->update([
        'wa_gateway_url' => 'https://gateway.example/wa',
        'wa_gateway_token' => 'device-token-001',
        'wa_gateway_key' => 'master-key',
        'wa_msg_randomize' => false,
        'wa_antispam_enabled' => false,
    ]);

    $service = WaGatewayService::forTenant($settings->fresh());
    expect($service)->not->toBeNull();

    $sent = $service->sendMessage('081234567890', 'Tes status gagal', ['event' => 'invoice_created']);

    expect($sent)->toBeFalse();

    Http::assertSentCount(1);
    $this->assertDatabaseHas('wa_blast_logs', [
        'owner_id' => $user->id,
        'event' => 'invoice_created',
        'status' => 'failed',
        'phone' => '6281234567890',
        'phone_normalized' => '6281234567890',
        'reason' => 'Status gateway: failed',
        'ref_id' => 'ref-failed-001',
    ]);
});

it('sends tenant session id to gateway for multi-session routing', function () {
    config()->set('wa.multi_session.public_url', 'https://gateway.example/wa');
    config()->set('wa.multi_session.auth_token', 'device-token-xyz');
    config()->set('wa.multi_session.master_key', '');

    Http::fake([
        'https://gateway.example/wa/api/v2/send-message' => Http::response([
            'status' => true,
            'data' => [
                'messages' => [
                    [
                        'status' => 'queued',
                        'ref_id' => 'ref-ok-001',
                    ],
                ],
            ],
        ], 200),
    ]);

    $tenant = User::factory()->create();
    $settings = TenantSettings::getOrCreate($tenant->id);
    $settings->update([
        'wa_gateway_url' => 'https://gateway.example/wa',
        'wa_gateway_token' => 'device-token-xyz',
        'wa_antispam_enabled' => false,
        'wa_msg_randomize' => false,
    ]);

    $service = WaGatewayService::forTenant($settings->fresh());
    expect($service)->not->toBeNull();

    $service->sendMessage('081234567890', 'Halo tenant', ['event' => 'blast']);

    Http::assertSent(function ($request) use ($tenant) {
        return str_contains($request->url(), '/api/v2/send-message')
            && ($request->header('X-Session-Id')[0] ?? null) === 'tenant-'.$tenant->id
            && data_get($request->data(), 'data.0.session') === 'tenant-'.$tenant->id;
    });
});

it('uses default tenant device session when available', function () {
    config()->set('wa.multi_session.public_url', 'https://gateway.example/wa');
    config()->set('wa.multi_session.auth_token', 'device-token-abc');

    Http::fake([
        'https://gateway.example/wa/api/v2/send-message' => Http::response([
            'status' => true,
            'data' => [
                'messages' => [
                    ['status' => 'queued', 'ref_id' => 'ref-device-001'],
                ],
            ],
        ], 200),
    ]);

    $tenant = User::factory()->create();
    WaMultiSessionDevice::query()->create([
        'user_id' => $tenant->id,
        'device_name' => 'Device A',
        'session_id' => 'tenant-'.$tenant->id.'-device-a',
        'is_default' => true,
        'is_active' => true,
    ]);

    $settings = TenantSettings::getOrCreate($tenant->id);
    $service = WaGatewayService::forTenant($settings);
    expect($service)->not->toBeNull();

    $service->sendMessage('081234567890', 'Tes device default', ['event' => 'blast']);

    Http::assertSent(function ($request) use ($tenant) {
        $expected = 'tenant-'.$tenant->id.'-device-a';

        return str_contains($request->url(), '/api/v2/send-message')
            && ($request->header('X-Session-Id')[0] ?? null) === $expected
            && data_get($request->data(), 'data.0.session') === $expected;
    });
});

it('distributes wa blast sends across connected active devices', function () {
    config()->set('wa.multi_session.public_url', 'https://gateway.example/wa');
    config()->set('wa.multi_session.auth_token', 'device-token-rr');

    Http::fake(function ($request) {
        if (str_contains($request->url(), '/api/v2/sessions/status')) {
            return Http::response([
                'status' => true,
                'data' => [
                    'status' => 'connected',
                ],
            ], 200);
        }

        if (str_contains($request->url(), '/api/v2/send-message')) {
            return Http::response([
                'status' => true,
                'data' => [
                    'messages' => [
                        ['status' => 'queued', 'ref_id' => 'ref-rr-001'],
                    ],
                ],
            ], 200);
        }

        return Http::response([], 404);
    });

    $tenant = User::factory()->create();
    WaMultiSessionDevice::query()->create([
        'user_id' => $tenant->id,
        'device_name' => 'Device A',
        'session_id' => 'tenant-'.$tenant->id.'-a',
        'is_default' => true,
        'is_active' => true,
    ]);
    WaMultiSessionDevice::query()->create([
        'user_id' => $tenant->id,
        'device_name' => 'Device B',
        'session_id' => 'tenant-'.$tenant->id.'-b',
        'is_default' => false,
        'is_active' => true,
    ]);

    $settings = TenantSettings::getOrCreate($tenant->id);
    $settings->update([
        'wa_blast_multi_device' => true,
        'wa_blast_message_variation' => false,
        'wa_blast_delay_min_ms' => 300,
        'wa_blast_delay_max_ms' => 300,
    ]);

    $service = WaGatewayService::forTenant($settings->fresh());
    expect($service)->not->toBeNull();

    $result = $service->sendBulk([
        ['phone' => '081111111111', 'message' => 'Pesan 1', 'name' => 'A'],
        ['phone' => '082222222222', 'message' => 'Pesan 2', 'name' => 'B'],
        ['phone' => '083333333333', 'message' => 'Pesan 3', 'name' => 'C'],
    ]);

    expect($result['success'])->toBe(3)
        ->and($result['failed'])->toBe(0);

    Http::assertSent(function ($request) use ($tenant) {
        return str_contains($request->url(), '/api/v2/send-message')
            && data_get($request->data(), 'data.0.session') === 'tenant-'.$tenant->id.'-a';
    });

    Http::assertSent(function ($request) use ($tenant) {
        return str_contains($request->url(), '/api/v2/send-message')
            && data_get($request->data(), 'data.0.session') === 'tenant-'.$tenant->id.'-b';
    });
});

it('adds professional message variation for wa blast when enabled', function () {
    config()->set('wa.multi_session.public_url', 'https://gateway.example/wa');
    config()->set('wa.multi_session.auth_token', 'device-token-var');

    Http::fake(function ($request) {
        if (str_contains($request->url(), '/api/v2/send-message')) {
            return Http::response([
                'status' => true,
                'data' => [
                    'messages' => [
                        ['status' => 'queued', 'ref_id' => 'ref-var-001'],
                    ],
                ],
            ], 200);
        }

        return Http::response([
            'status' => true,
            'data' => [
                'status' => 'connected',
            ],
        ], 200);
    });

    $tenant = User::factory()->create();
    $settings = TenantSettings::getOrCreate($tenant->id);
    $settings->update([
        'wa_blast_multi_device' => false,
        'wa_blast_message_variation' => true,
        'wa_blast_delay_min_ms' => 300,
        'wa_blast_delay_max_ms' => 300,
    ]);

    $service = WaGatewayService::forTenant($settings->fresh());
    expect($service)->not->toBeNull();

    $service->sendBulk([
        ['phone' => '081234567890', 'message' => 'Informasi jadwal maintenance malam ini.', 'name' => 'Pelanggan A'],
    ]);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/api/v2/send-message')) {
            return false;
        }

        $message = (string) data_get($request->data(), 'data.0.message', '');

        return str_contains($message, 'Bapak/Ibu Pelanggan A,')
            && str_contains($message, 'Informasi jadwal maintenance malam ini.')
            && (
                str_contains($message, 'Terima kasih atas perhatian Anda.')
                || str_contains($message, 'Jika ada pertanyaan, silakan balas pesan ini.')
                || str_contains($message, 'Kami siap membantu jika diperlukan.')
            );
    });
});

it('does not add duplicate greeting and closing when message already contains both', function () {
    config()->set('wa.multi_session.public_url', 'https://gateway.example/wa');
    config()->set('wa.multi_session.auth_token', 'device-token-var');

    Http::fake([
        'https://gateway.example/wa/api/v2/send-message' => Http::response([
            'status' => true,
            'data' => [
                'messages' => [
                    ['status' => 'queued', 'ref_id' => 'ref-var-002'],
                ],
            ],
        ], 200),
    ]);

    $tenant = User::factory()->create();
    $settings = TenantSettings::getOrCreate($tenant->id);
    $settings->update([
        'wa_blast_multi_device' => false,
        'wa_blast_message_variation' => true,
        'wa_msg_randomize' => false,
        'wa_blast_delay_min_ms' => 300,
        'wa_blast_delay_max_ms' => 300,
    ]);

    $service = WaGatewayService::forTenant($settings->fresh());
    expect($service)->not->toBeNull();

    $baseMessage = "Halo Bapak/Ibu Pelanggan B,\n\nInformasi maintenance jaringan malam ini.\n\nTerima kasih atas perhatian Anda.";
    $service->sendBulk([
        ['phone' => '081234567891', 'message' => $baseMessage, 'name' => 'Pelanggan B'],
    ]);

    Http::assertSent(function ($request) use ($baseMessage) {
        if (! str_contains($request->url(), '/api/v2/send-message')) {
            return false;
        }

        $message = (string) data_get($request->data(), 'data.0.message', '');

        return $message === $baseMessage;
    });
});

it('uses dynamic time greeting for variation opening', function () {
    Carbon::setTestNow('2026-03-28 20:00:00');

    try {
        config()->set('wa.multi_session.public_url', 'https://gateway.example/wa');
        config()->set('wa.multi_session.auth_token', 'device-token-var');

        Http::fake([
            'https://gateway.example/wa/api/v2/send-message' => Http::response([
                'status' => true,
                'data' => [
                    'messages' => [
                        ['status' => 'queued', 'ref_id' => 'ref-var-003'],
                    ],
                ],
            ], 200),
        ]);

        $tenant = User::factory()->create();
        $settings = TenantSettings::getOrCreate($tenant->id);
        $settings->update([
            'wa_blast_multi_device' => false,
            'wa_blast_message_variation' => true,
            'wa_msg_randomize' => false,
            'wa_blast_delay_min_ms' => 300,
            'wa_blast_delay_max_ms' => 300,
        ]);

        $service = WaGatewayService::forTenant($settings->fresh());
        expect($service)->not->toBeNull();

        $service->sendBulk([
            ['phone' => '081234567892', 'message' => 'Pesan 1', 'name' => 'Pelanggan A'],
            ['phone' => '081234567893', 'message' => 'Pesan 2', 'name' => 'Pelanggan B'],
            ['phone' => '081234567894', 'message' => 'Pesan 3', 'name' => 'Pelanggan C'],
        ]);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/api/v2/send-message')) {
                return false;
            }

            $message = (string) data_get($request->data(), 'data.0.message', '');

            return str_starts_with($message, 'Selamat malam Bapak/Ibu Pelanggan A,')
                && str_contains($message, 'Pesan 1');
        });
    } finally {
        Carbon::setTestNow();
    }
});

it('keeps natural variation randomized per phone when multi device distribution is enabled', function () {
    config()->set('wa.multi_session.public_url', 'https://gateway.example/wa');
    config()->set('wa.multi_session.auth_token', 'device-token-rr-var');

    Http::fake(function ($request) {
        if (str_contains($request->url(), '/api/v2/sessions/status')) {
            return Http::response([
                'status' => true,
                'data' => [
                    'status' => 'connected',
                ],
            ], 200);
        }

        if (str_contains($request->url(), '/api/v2/send-message')) {
            return Http::response([
                'status' => true,
                'data' => [
                    'messages' => [
                        ['status' => 'queued', 'ref_id' => 'ref-rr-var-001'],
                    ],
                ],
            ], 200);
        }

        return Http::response([], 404);
    });

    $tenant = User::factory()->create();
    WaMultiSessionDevice::query()->create([
        'user_id' => $tenant->id,
        'device_name' => 'Device A',
        'session_id' => 'tenant-'.$tenant->id.'-a',
        'is_default' => true,
        'is_active' => true,
    ]);
    WaMultiSessionDevice::query()->create([
        'user_id' => $tenant->id,
        'device_name' => 'Device B',
        'session_id' => 'tenant-'.$tenant->id.'-b',
        'is_default' => false,
        'is_active' => true,
    ]);

    $settings = TenantSettings::getOrCreate($tenant->id);
    $settings->update([
        'wa_blast_multi_device' => true,
        'wa_blast_message_variation' => true,
        'wa_msg_randomize' => false,
        'wa_blast_delay_min_ms' => 300,
        'wa_blast_delay_max_ms' => 300,
    ]);

    $service = WaGatewayService::forTenant($settings->fresh());
    expect($service)->not->toBeNull();

    $service->sendBulk([
        ['phone' => '081111111111', 'message' => 'Info maintenance jaringan malam ini.', 'name' => ''],
        ['phone' => '082222222222', 'message' => 'Info maintenance jaringan malam ini.', 'name' => ''],
        ['phone' => '083333333333', 'message' => 'Info maintenance jaringan malam ini.', 'name' => ''],
    ]);

    $sendRequests = collect(Http::recorded())
        ->map(fn (array $recorded) => $recorded[0])
        ->filter(fn ($request) => str_contains($request->url(), '/api/v2/send-message'))
        ->values();

    expect($sendRequests)->toHaveCount(3);

    $sessionsUsed = $sendRequests
        ->map(fn ($request) => (string) data_get($request->data(), 'data.0.session'))
        ->filter();

    expect($sessionsUsed->contains('tenant-'.$tenant->id.'-a'))->toBeTrue()
        ->and($sessionsUsed->contains('tenant-'.$tenant->id.'-b'))->toBeTrue();

    $messages = $sendRequests
        ->map(fn ($request) => (string) data_get($request->data(), 'data.0.message'))
        ->filter();

    expect($messages->unique()->count())->toBeGreaterThan(1);
});
