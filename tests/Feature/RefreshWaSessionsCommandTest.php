<?php

use App\Models\WaMultiSessionDevice;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('wa_multi_session_devices');
    Schema::dropIfExists('wa_multi_session_auth_store');

    Schema::create('wa_multi_session_devices', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('session_id', 150)->unique();
        $table->string('device_name', 120);
        $table->boolean('is_default')->default(false);
        $table->boolean('is_active')->default(true);
        $table->string('last_status', 60)->nullable();
        $table->timestamp('last_seen_at')->nullable();
        $table->json('meta')->nullable();
        $table->timestamps();
    });

    Schema::create('wa_multi_session_auth_store', function (Blueprint $table): void {
        $table->string('id', 191);
        $table->string('session_id', 191);
        $table->string('category', 120)->nullable();
        $table->longText('value')->nullable();
        $table->timestamps();
        $table->primary(['id', 'session_id']);
    });
});

it('does not auto reconnect disconnected sessions when last error is logged out', function () {
    config()->set('wa.multi_session.host', '127.0.0.1');
    config()->set('wa.multi_session.port', 3100);
    config()->set('wa.multi_session.auth_token', 'wa-token-test');

    WaMultiSessionDevice::query()->create([
        'user_id' => 1,
        'session_id' => 'tenant-1-device-1',
        'device_name' => 'Device 1',
        'is_active' => true,
    ]);

    Http::fake([
        'http://127.0.0.1:3100/api/v2/sessions/status*' => Http::response([
            'status' => true,
            'data' => [
                'session' => 'tenant-1-device-1',
                'status' => 'disconnected',
                'last_error' => 'loggedOut',
                'updated_at' => now()->subMinutes(30)->toISOString(),
            ],
        ], 200),
        'http://127.0.0.1:3100/api/v2/sessions/restart' => Http::response([
            'status' => true,
        ], 200),
    ]);

    $this->artisan('wa-gateway:refresh-sessions --stale-minutes=120')
        ->assertSuccessful();

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/api/v2/sessions/restart');
    });
});

it('auto reconnects disconnected sessions when last error is not logged out', function () {
    config()->set('wa.multi_session.host', '127.0.0.1');
    config()->set('wa.multi_session.port', 3100);
    config()->set('wa.multi_session.auth_token', 'wa-token-test');

    WaMultiSessionDevice::query()->create([
        'user_id' => 1,
        'session_id' => 'tenant-1-device-2',
        'device_name' => 'Device 2',
        'is_active' => true,
    ]);

    Http::fake([
        'http://127.0.0.1:3100/api/v2/sessions/status*' => Http::response([
            'status' => true,
            'data' => [
                'session' => 'tenant-1-device-2',
                'status' => 'disconnected',
                'last_error' => 'connectionLost',
                'updated_at' => now()->subMinutes(30)->toISOString(),
            ],
        ], 200),
        'http://127.0.0.1:3100/api/v2/sessions/restart' => Http::response([
            'status' => true,
        ], 200),
    ]);

    $this->artisan('wa-gateway:refresh-sessions --stale-minutes=120')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v2/sessions/restart');
    });
});

it('does not auto reconnect disconnected sessions when last error is connection replaced', function () {
    config()->set('wa.multi_session.host', '127.0.0.1');
    config()->set('wa.multi_session.port', 3100);
    config()->set('wa.multi_session.auth_token', 'wa-token-test');

    WaMultiSessionDevice::query()->create([
        'user_id' => 1,
        'session_id' => 'tenant-1-device-3',
        'device_name' => 'Device 3',
        'is_active' => true,
    ]);

    Http::fake([
        'http://127.0.0.1:3100/api/v2/sessions/status*' => Http::response([
            'status' => true,
            'data' => [
                'session' => 'tenant-1-device-3',
                'status' => 'disconnected',
                'last_error' => 'connectionReplaced',
                'updated_at' => now()->subMinutes(30)->toISOString(),
            ],
        ], 200),
        'http://127.0.0.1:3100/api/v2/sessions/restart' => Http::response([
            'status' => true,
        ], 200),
    ]);

    $this->artisan('wa-gateway:refresh-sessions --stale-minutes=120')
        ->assertSuccessful();

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/api/v2/sessions/restart');
    });
});

it('does not restart quiet connected sessions only because the last status update is old', function () {
    config()->set('wa.multi_session.host', '127.0.0.1');
    config()->set('wa.multi_session.port', 3100);
    config()->set('wa.multi_session.auth_token', 'wa-token-test');

    WaMultiSessionDevice::query()->create([
        'user_id' => 1,
        'session_id' => 'tenant-1-device-4',
        'device_name' => 'Device 4',
        'is_active' => true,
    ]);

    Http::fake([
        'http://127.0.0.1:3100/api/v2/sessions/status*' => Http::response([
            'status' => true,
            'data' => [
                'session' => 'tenant-1-device-4',
                'status' => 'connected',
                'last_error' => null,
                'updated_at' => now()->subMinutes(180)->toISOString(),
            ],
        ], 200),
        'http://127.0.0.1:3100/api/v2/sessions/restart' => Http::response([
            'status' => true,
        ], 200),
    ]);

    $this->artisan('wa-gateway:refresh-sessions --stale-minutes=120')
        ->assertSuccessful();

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/api/v2/sessions/restart');
    });
});
