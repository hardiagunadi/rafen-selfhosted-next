<?php

use App\Models\TenantSettings;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('tenant_settings');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role')->nullable();
        $table->boolean('is_super_admin')->default(false);
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->string('subscription_status')->nullable();
        $table->dateTime('subscription_expires_at')->nullable();
        $table->integer('trial_days_remaining')->default(0);
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('tenant_settings', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id')->unique();
        $table->string('business_name')->nullable();
        $table->string('business_phone')->nullable();
        $table->string('invoice_prefix')->nullable();
        $table->boolean('enable_manual_payment')->default(true);
        $table->integer('payment_expiry_hours')->default(24);
        $table->boolean('auto_isolate_unpaid')->default(true);
        $table->integer('grace_period_days')->default(3);
        $table->boolean('module_hotspot_enabled')->default(true);
        $table->timestamps();
    });
});

it('sends meta cloud api test from tenant settings endpoint', function () {
    config()->set('services.meta_whatsapp.api_version', 'v23.0');
    config()->set('services.meta_whatsapp.access_token', 'meta-token-001');
    config()->set('services.meta_whatsapp.phone_number_id', '1234567890');

    Http::fake([
        'https://graph.facebook.com/v23.0/1234567890/messages' => Http::response([
            'messages' => [['id' => 'wamid.meta.001']],
        ], 200),
    ]);

    $superAdmin = User::query()->create([
        'name' => 'Super Admin',
        'email' => 'super@rafen.test',
        'password' => 'password',
        'role' => 'administrator',
        'is_super_admin' => true,
    ]);

    $tenant = User::query()->create([
        'name' => 'Tenant A',
        'email' => 'tenant@rafen.test',
        'password' => 'password',
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(10),
    ]);

    TenantSettings::query()->create([
        'user_id' => $tenant->id,
        'business_name' => 'Tenant A Net',
        'business_phone' => '0811222333',
        'invoice_prefix' => 'INV',
    ]);

    $response = $this->actingAs($superAdmin)->postJson(route('tenant-settings.test-wa-meta'), [
        'tenant_id' => $tenant->id,
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
            'recipient' => '62811222333',
        ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://graph.facebook.com/v23.0/1234567890/messages'
            && data_get($request->data(), 'to') === '62811222333'
            && str_contains((string) data_get($request->data(), 'text.body', ''), 'Test Meta Cloud API');
    });
});

it('returns validation error when target phone is unavailable', function () {
    config()->set('services.meta_whatsapp.api_version', 'v23.0');
    config()->set('services.meta_whatsapp.access_token', 'meta-token-001');
    config()->set('services.meta_whatsapp.phone_number_id', '1234567890');

    Http::fake();

    $superAdmin = User::query()->create([
        'name' => 'Super Admin',
        'email' => 'super2@rafen.test',
        'password' => 'password',
        'role' => 'administrator',
        'is_super_admin' => true,
    ]);

    $tenant = User::query()->create([
        'name' => 'Tenant B',
        'email' => 'tenant2@rafen.test',
        'password' => 'password',
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(10),
    ]);

    TenantSettings::query()->create([
        'user_id' => $tenant->id,
        'business_name' => 'Tenant B Net',
        'invoice_prefix' => 'INV',
    ]);

    $response = $this->actingAs($superAdmin)->postJson(route('tenant-settings.test-wa-meta'), [
        'tenant_id' => $tenant->id,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Nomor tujuan kosong. Isi nomor bisnis di pengaturan atau kirim nomor manual saat test.',
        ]);

    Http::assertNothingSent();
});
