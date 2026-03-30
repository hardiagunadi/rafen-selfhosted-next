<?php

use App\Models\WaMultiSessionDevice;
use App\Services\WaGatewayService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Cache::flush();

    Schema::dropIfExists('wa_multi_session_devices');
    Schema::dropIfExists('wa_blast_logs');

    Schema::create('wa_multi_session_devices', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('session_id', 150)->unique();
        $table->string('device_name', 120);
        $table->boolean('is_default')->default(false);
        $table->boolean('is_platform_device')->default(false);
        $table->boolean('is_active')->default(true);
        $table->string('last_status', 60)->nullable();
        $table->timestamp('last_seen_at')->nullable();
        $table->json('meta')->nullable();
        $table->timestamps();
    });

    Schema::create('wa_blast_logs', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('owner_id')->nullable();
        $table->unsignedBigInteger('sent_by_id')->nullable();
        $table->string('sent_by_name')->nullable();
        $table->string('event', 60)->nullable();
        $table->string('phone')->nullable();
        $table->string('phone_normalized')->nullable();
        $table->string('status', 30)->nullable();
        $table->text('reason')->nullable();
        $table->string('invoice_number')->nullable();
        $table->unsignedBigInteger('invoice_id')->nullable();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('username')->nullable();
        $table->string('customer_name')->nullable();
        $table->longText('message')->nullable();
        $table->string('ref_id')->nullable();
        $table->timestamps();
    });
});

it('keeps sender session sticky for the same recipient phone in blast conversation', function () {
    $ownerId = 101;

    WaMultiSessionDevice::query()->create([
        'user_id' => $ownerId,
        'device_name' => 'Device A',
        'session_id' => 'tenant-'.$ownerId.'-a',
        'is_default' => true,
        'is_active' => true,
    ]);
    WaMultiSessionDevice::query()->create([
        'user_id' => $ownerId,
        'device_name' => 'Device B',
        'session_id' => 'tenant-'.$ownerId.'-b',
        'is_default' => false,
        'is_active' => true,
    ]);

    $sessionsPerPhone = [];
    Http::fake(function ($request) use (&$sessionsPerPhone) {
        if (str_contains($request->url(), '/api/v2/sessions/status')) {
            return Http::response([
                'status' => true,
                'data' => [
                    'status' => 'connected',
                ],
            ], 200);
        }

        if (str_contains($request->url(), '/api/v2/send-message')) {
            $sessionId = (string) data_get($request->data(), 'data.0.session', '');
            $phone = (string) data_get($request->data(), 'data.0.phone', '');
            $sessionsPerPhone[$phone][] = $sessionId;

            return Http::response([
                'status' => true,
                'data' => [
                    'messages' => [
                        ['status' => 'queued', 'ref_id' => 'ref-sticky'],
                    ],
                ],
            ], 200);
        }

        return Http::response([], 404);
    });

    $service = new WaGatewayService('https://gateway.example/wa', 'device-token-sticky');
    $reflector = new ReflectionClass($service);
    $set = function (string $property, mixed $value) use ($reflector, $service): void {
        $prop = $reflector->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($service, $value);
    };

    $set('ownerId', $ownerId);
    $set('blastMultiDevice', true);
    $set('blastNaturalVariation', false);
    $set('blastDelayMinMs', 2000);
    $set('blastDelayMaxMs', 3000);
    $set('randomize', false);
    $set('sessionId', 'tenant-'.$ownerId.'-a');

    $result = $service->sendBulk([
        ['phone' => '081111111111', 'message' => 'Pesan 1', 'name' => 'A'],
        ['phone' => '082222222222', 'message' => 'Pesan 2', 'name' => 'B'],
        ['phone' => '081111111111', 'message' => 'Pesan 3', 'name' => 'A'],
    ]);

    $stickyPhone = '6281111111111';
    $stickySessions = array_values(array_unique($sessionsPerPhone[$stickyPhone] ?? []));

    expect($result['success'])->toBe(3)
        ->and($result['failed'])->toBe(0)
        ->and(count($stickySessions))->toBe(1);
});

it('can clear sticky sender mapping for a phone number', function () {
    $ownerId = 202;
    $phone = '081234567890';
    $normalized = '6281234567890';
    $cacheKey = 'wa_sticky_sender_'.$ownerId.'_'.md5($normalized);

    Cache::put($cacheKey, 'tenant-'.$ownerId.'-a', now()->addMinutes(30));

    $firstClear = WaGatewayService::clearStickySenderForPhone($ownerId, $phone);
    $secondClear = WaGatewayService::clearStickySenderForPhone($ownerId, $phone);

    expect($firstClear)->toBeTrue()
        ->and(Cache::has($cacheKey))->toBeFalse()
        ->and($secondClear)->toBeFalse();
});

it('cools down failed blast device and routes next messages to healthy device', function () {
    $ownerId = 99;

    WaMultiSessionDevice::query()->create([
        'user_id' => $ownerId,
        'device_name' => 'Device A',
        'session_id' => 'tenant-'.$ownerId.'-a',
        'is_default' => true,
        'is_active' => true,
    ]);
    WaMultiSessionDevice::query()->create([
        'user_id' => $ownerId,
        'device_name' => 'Device B',
        'session_id' => 'tenant-'.$ownerId.'-b',
        'is_default' => false,
        'is_active' => true,
    ]);

    $attemptBySession = [];
    Http::fake(function ($request) use (&$attemptBySession) {
        if (str_contains($request->url(), '/api/v2/sessions/status')) {
            return Http::response([
                'status' => true,
                'data' => [
                    'status' => 'connected',
                ],
            ], 200);
        }

        if (str_contains($request->url(), '/api/v2/send-message')) {
            $sessionId = (string) data_get($request->data(), 'data.0.session', '');
            $attemptBySession[$sessionId] = ((int) ($attemptBySession[$sessionId] ?? 0)) + 1;

            if (str_ends_with($sessionId, '-a')) {
                return Http::response([
                    'status' => true,
                    'data' => [
                        'messages' => [
                            ['status' => 'failed', 'ref_id' => 'ref-failed-a'],
                        ],
                    ],
                ], 200);
            }

            return Http::response([
                'status' => true,
                'data' => [
                    'messages' => [
                        ['status' => 'queued', 'ref_id' => 'ref-ok-b'],
                    ],
                ],
            ], 200);
        }

        return Http::response([], 404);
    });

    $service = new WaGatewayService('https://gateway.example/wa', 'device-token-rotation');
    $reflector = new ReflectionClass($service);

    $set = function (string $property, mixed $value) use ($reflector, $service): void {
        $prop = $reflector->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($service, $value);
    };

    $set('ownerId', $ownerId);
    $set('blastMultiDevice', true);
    $set('blastNaturalVariation', false);
    $set('blastDelayMinMs', 2000);
    $set('blastDelayMaxMs', 3000);
    $set('randomize', false);
    $set('sessionId', 'tenant-'.$ownerId.'-a');

    $result = $service->sendBulk([
        ['phone' => '081111111111', 'message' => 'Pesan 1', 'name' => 'A'],
        ['phone' => '082222222222', 'message' => 'Pesan 2', 'name' => 'B'],
    ]);

    expect($result['success'])->toBe(2)
        ->and($result['failed'])->toBe(0)
        ->and((int) ($attemptBySession['tenant-'.$ownerId.'-a'] ?? 0))->toBe(1)
        ->and((int) ($attemptBySession['tenant-'.$ownerId.'-b'] ?? 0))->toBeGreaterThanOrEqual(2);
});

it('distributes blast across connected devices with round robin and failover enabled', function () {
    $ownerId = 88;

    WaMultiSessionDevice::query()->create([
        'user_id' => $ownerId,
        'device_name' => 'Device A',
        'session_id' => 'tenant-'.$ownerId.'-a',
        'is_default' => true,
        'is_active' => true,
    ]);
    WaMultiSessionDevice::query()->create([
        'user_id' => $ownerId,
        'device_name' => 'Device B',
        'session_id' => 'tenant-'.$ownerId.'-b',
        'is_default' => false,
        'is_active' => true,
    ]);

    $attemptBySession = [];
    Http::fake(function ($request) use (&$attemptBySession) {
        if (str_contains($request->url(), '/api/v2/sessions/status')) {
            return Http::response([
                'status' => true,
                'data' => [
                    'status' => 'connected',
                ],
            ], 200);
        }

        if (str_contains($request->url(), '/api/v2/send-message')) {
            $sessionId = (string) data_get($request->data(), 'data.0.session', '');
            $attemptBySession[$sessionId] = ((int) ($attemptBySession[$sessionId] ?? 0)) + 1;

            return Http::response([
                'status' => true,
                'data' => [
                    'messages' => [
                        ['status' => 'queued', 'ref_id' => 'ref-ok'],
                    ],
                ],
            ], 200);
        }

        return Http::response([], 404);
    });

    $service = new WaGatewayService('https://gateway.example/wa', 'device-token-rr');
    $reflector = new ReflectionClass($service);
    $set = function (string $property, mixed $value) use ($reflector, $service): void {
        $prop = $reflector->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($service, $value);
    };

    $set('ownerId', $ownerId);
    $set('blastMultiDevice', true);
    $set('blastNaturalVariation', false);
    $set('blastDelayMinMs', 2000);
    $set('blastDelayMaxMs', 3000);
    $set('randomize', false);
    $set('sessionId', 'tenant-'.$ownerId.'-a');

    $result = $service->sendBulk([
        ['phone' => '081111111111', 'message' => 'Pesan 1', 'name' => 'A'],
        ['phone' => '082222222222', 'message' => 'Pesan 2', 'name' => 'B'],
        ['phone' => '083333333333', 'message' => 'Pesan 3', 'name' => 'C'],
    ]);

    expect($result['success'])->toBe(3)
        ->and($result['failed'])->toBe(0)
        ->and((int) ($attemptBySession['tenant-'.$ownerId.'-a'] ?? 0))->toBeGreaterThan(0)
        ->and((int) ($attemptBySession['tenant-'.$ownerId.'-b'] ?? 0))->toBeGreaterThan(0);
});

it('limits warmup device usage per batch using device meta flag', function () {
    $ownerId = 100;

    WaMultiSessionDevice::query()->create([
        'user_id' => $ownerId,
        'device_name' => 'Warmup Device',
        'session_id' => 'tenant-'.$ownerId.'-warmup',
        'is_default' => true,
        'is_active' => true,
        'meta' => [
            'is_warmup' => true,
            'warmup_max_per_batch' => 1,
        ],
    ]);
    WaMultiSessionDevice::query()->create([
        'user_id' => $ownerId,
        'device_name' => 'Stable Device',
        'session_id' => 'tenant-'.$ownerId.'-stable',
        'is_default' => false,
        'is_active' => true,
        'meta' => [],
    ]);

    $attemptBySession = [];
    Http::fake(function ($request) use (&$attemptBySession) {
        if (str_contains($request->url(), '/api/v2/sessions/status')) {
            return Http::response([
                'status' => true,
                'data' => [
                    'status' => 'connected',
                ],
            ], 200);
        }

        if (str_contains($request->url(), '/api/v2/send-message')) {
            $sessionId = (string) data_get($request->data(), 'data.0.session', '');
            $attemptBySession[$sessionId] = ((int) ($attemptBySession[$sessionId] ?? 0)) + 1;

            return Http::response([
                'status' => true,
                'data' => [
                    'messages' => [
                        ['status' => 'queued', 'ref_id' => 'ref-ok'],
                    ],
                ],
            ], 200);
        }

        return Http::response([], 404);
    });

    $service = new WaGatewayService('https://gateway.example/wa', 'device-token-warmup');
    $reflector = new ReflectionClass($service);

    $set = function (string $property, mixed $value) use ($reflector, $service): void {
        $prop = $reflector->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($service, $value);
    };

    $set('ownerId', $ownerId);
    $set('blastMultiDevice', true);
    $set('blastNaturalVariation', false);
    $set('blastDelayMinMs', 2000);
    $set('blastDelayMaxMs', 3000);
    $set('randomize', false);
    $set('sessionId', 'tenant-'.$ownerId.'-warmup');

    $result = $service->sendBulk([
        ['phone' => '081111111111', 'message' => 'Pesan 1', 'name' => 'A'],
        ['phone' => '082222222222', 'message' => 'Pesan 2', 'name' => 'B'],
        ['phone' => '083333333333', 'message' => 'Pesan 3', 'name' => 'C'],
    ]);

    expect($result['success'])->toBe(3)
        ->and($result['failed'])->toBe(0)
        ->and((int) ($attemptBySession['tenant-'.$ownerId.'-warmup'] ?? 0))->toBeLessThanOrEqual(1)
        ->and((int) ($attemptBySession['tenant-'.$ownerId.'-stable'] ?? 0))->toBeGreaterThanOrEqual(2);
});

it('supports automatic warmup ramp and stops when warmup period expires', function () {
    $service = new WaGatewayService('https://gateway.example/wa', 'device-token-warmup-auto');
    $reflector = new ReflectionClass($service);
    $method = $reflector->getMethod('resolveWarmupConfig');
    $method->setAccessible(true);

    $autoConfig = $method->invoke($service, [
        'is_warmup' => true,
        'warmup_auto' => true,
        'warmup_started_at' => now()->subDays(5)->toIso8601String(),
        'warmup_until' => now()->addDays(7)->toIso8601String(),
        'warmup_max_per_batch' => 0,
    ]);
    $expiredConfig = $method->invoke($service, [
        'is_warmup' => true,
        'warmup_auto' => false,
        'warmup_until' => now()->subDay()->toIso8601String(),
        'warmup_max_per_batch' => 9,
    ]);

    expect($autoConfig['active'])->toBeTrue()
        ->and($autoConfig['max_per_batch'])->toBe(3)
        ->and($expiredConfig['active'])->toBeFalse()
        ->and($expiredConfig['max_per_batch'])->toBe(9);
});

it('uses platform device session profile when tenant is configured to use platform device', function () {
    $ownerId = 777;
    $platformOwnerId = 1;

    WaMultiSessionDevice::query()->create([
        'user_id' => $ownerId,
        'device_name' => 'Tenant Device A',
        'session_id' => 'tenant-'.$ownerId.'-a',
        'is_default' => true,
        'is_active' => true,
        'meta' => [],
    ]);
    WaMultiSessionDevice::query()->create([
        'user_id' => $ownerId,
        'device_name' => 'Tenant Device B',
        'session_id' => 'tenant-'.$ownerId.'-b',
        'is_default' => false,
        'is_active' => true,
        'meta' => [],
    ]);
    $platformDevice = WaMultiSessionDevice::query()->create([
        'user_id' => $platformOwnerId,
        'device_name' => 'Platform Shared Device',
        'session_id' => 'platform-shared-session',
        'is_default' => true,
        'is_active' => true,
        'is_platform_device' => true,
        'meta' => [
            'is_warmup' => true,
            'warmup_max_per_batch' => 5,
        ],
    ]);

    $service = new WaGatewayService('https://gateway.example/wa', 'device-token-platform');
    $reflector = new ReflectionClass($service);
    $set = function (string $property, mixed $value) use ($reflector, $service): void {
        $prop = $reflector->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($service, $value);
    };

    $set('ownerId', $ownerId);
    $set('blastMultiDevice', true);
    $set('sessionId', 'platform-shared-session');
    $set('platformDeviceId', (int) $platformDevice->id);

    $method = $reflector->getMethod('resolveBlastSessionProfiles');
    $method->setAccessible(true);
    $profiles = $method->invoke($service);

    expect($profiles)->toHaveCount(1)
        ->and($profiles[0]['session_id'])->toBe('platform-shared-session')
        ->and($profiles[0]['warmup_active'])->toBeTrue()
        ->and($profiles[0]['warmup_max_per_batch'])->toBe(5);
});
