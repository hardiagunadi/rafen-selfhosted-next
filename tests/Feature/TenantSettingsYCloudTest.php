<?php

use App\Models\TenantSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeWaTenant(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ], $attributes));
}

it('applies safe throttling defaults when local provider settings are saved', function () {
    $tenantAdmin = makeWaTenant();

    TenantSettings::getOrCreate($tenantAdmin->id)->update([
        'wa_antispam_delay_ms' => 2000,
        'wa_blast_delay_min_ms' => 2000,
        'wa_blast_delay_max_ms' => 3200,
    ]);

    $this->actingAs($tenantAdmin)
        ->from(route('wa-gateway.index'))
        ->put(route('tenant-settings.update-wa'), [
            'wa_provider' => 'local',
            'wa_antispam_delay_ms' => '2.5',
            'wa_blast_delay_min_ms' => '3.0',
            'wa_blast_delay_max_ms' => '4.2',
            'wa_antispam_max_per_minute' => '10',
        ])
        ->assertRedirect(route('wa-gateway.index'));

    $this->assertDatabaseHas('tenant_settings', [
        'user_id' => $tenantAdmin->id,
        'wa_provider' => 'local',
        'wa_antispam_delay_ms' => 8000,
        'wa_antispam_max_per_minute' => 4,
        'wa_blast_delay_min_ms' => 12000,
        'wa_blast_delay_max_ms' => 20000,
    ]);
});

it('sends ycloud test message from tenant settings endpoint', function () {
    config()->set('services.ycloud_whatsapp.base_url', 'https://api.ycloud.com/v2');

    $superAdmin = makeWaTenant([
        'is_super_admin' => true,
    ]);
    $tenant = makeWaTenant([
        'email' => 'tenant-ycloud@rafen.test',
    ]);

    TenantSettings::getOrCreate($tenant->id)->update([
        'business_name' => 'Tenant YCloud',
        'business_phone' => '0811222333',
        'wa_provider' => 'ycloud',
        'ycloud_enabled' => true,
        'ycloud_api_key' => 'tenant-key-001',
        'ycloud_phone_number_id' => 'pn_tenant_001',
        'ycloud_business_number' => '62899111222333',
    ]);

    Http::fake([
        'https://api.ycloud.com/v2/whatsapp/messages/sendDirectly' => Http::response([
            'whatsappMessage' => [
                'id' => 'wamid.ycloud.test.001',
                'status' => 'accepted',
            ],
        ], 200),
    ]);

    $this->actingAs($superAdmin)
        ->postJson(route('tenant-settings.test-wa-ycloud'), [
            'tenant_id' => $tenant->id,
        ])
        ->assertSuccessful()
        ->assertJson([
            'success' => true,
            'recipient' => '62811222333',
        ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.ycloud.com/v2/whatsapp/messages/sendDirectly'
            && $request->hasHeader('X-API-Key', 'tenant-key-001')
            && data_get($request->data(), 'from') === 'pn_tenant_001'
            && data_get($request->data(), 'to') === '62811222333'
            && str_contains((string) data_get($request->data(), 'text.body', ''), 'Test YCloud');
    });
});

it('fetches ycloud phone numbers from tenant settings endpoint', function () {
    config()->set('services.ycloud_whatsapp.base_url', 'https://api.ycloud.com/v2');

    $tenant = makeWaTenant();

    TenantSettings::getOrCreate($tenant->id)->update([
        'wa_provider' => 'ycloud',
        'ycloud_enabled' => true,
        'ycloud_api_key' => 'tenant-key-001',
        'ycloud_waba_id' => 'waba_001',
    ]);

    Http::fake([
        'https://api.ycloud.com/v2/whatsapp/phoneNumbers*' => Http::response([
            'data' => [[
                'id' => 'pn_001',
                'displayPhoneNumber' => '6281234567890',
                'wabaId' => 'waba_001',
                'verifiedName' => 'Tenant Line 1',
                'status' => 'CONNECTED',
            ]],
        ], 200),
    ]);

    $this->actingAs($tenant)
        ->postJson(route('tenant-settings.fetch-ycloud-phone-numbers'))
        ->assertSuccessful()
        ->assertJsonPath('phone_numbers.0.id', 'pn_001')
        ->assertJsonPath('phone_numbers.0.waba_id', 'waba_001')
        ->assertJsonPath('phone_numbers.0.phone_number', '6281234567890');
});
