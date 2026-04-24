<?php

use App\Models\HotspotProfile;
use App\Models\TenantSettings;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeVoucherOwner(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ], $attributes));
}

it('sends voucher code through whatsapp gateway', function () {
    config()->set('wa.multi_session.public_url', '');
    config()->set('wa.multi_session.auth_token', '');
    config()->set('wa.multi_session.master_key', '');

    $owner = makeVoucherOwner();
    $profile = HotspotProfile::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Voucher 1 Hari',
        'profile_group_id' => null,
    ]);

    TenantSettings::getOrCreate($owner->id)->update([
        'wa_gateway_url' => 'https://gateway.example/wa',
        'wa_gateway_token' => 'device-token-voucher',
        'wa_gateway_key' => 'master-key-voucher',
        'wa_msg_randomize' => false,
        'wa_antispam_enabled' => false,
    ]);

    $voucher = Voucher::query()->create([
        'owner_id' => $owner->id,
        'hotspot_profile_id' => $profile->id,
        'profile_group_id' => null,
        'batch_name' => 'Batch Promo',
        'code' => 'VC123456',
        'status' => 'unused',
        'username' => 'VC123456',
        'password' => 'VC123456',
    ]);

    Http::fake([
        'https://gateway.example/wa/api/v2/send-message' => Http::response([
            'status' => true,
            'data' => [
                'messages' => [
                    ['status' => 'queued', 'ref_id' => 'voucher-send-ref-001'],
                ],
            ],
        ], 200),
    ]);

    $this->actingAs($owner)
        ->postJson(route('vouchers.send-wa', $voucher), [
            'phone' => '081234567890',
        ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'Kode voucher berhasil dikirim ke 081234567890');

    Http::assertSent(function ($request) {
        $message = (string) data_get($request->data(), 'data.0.message', '');

        return $request->url() === 'https://gateway.example/wa/api/v2/send-message'
            && data_get($request->data(), 'data.0.phone') === '6281234567890'
            && str_contains($message, 'VC123456')
            && str_contains($message, 'Voucher 1 Hari');
    });

    $this->assertDatabaseHas('activity_logs', [
        'owner_id' => $owner->id,
        'user_id' => $owner->id,
        'action' => 'send_wa',
        'subject_type' => 'Voucher',
        'subject_id' => $voucher->id,
        'subject_label' => 'VC123456',
    ]);

    $this->assertDatabaseHas('wa_blast_logs', [
        'owner_id' => $owner->id,
        'event' => 'voucher_code',
        'provider' => 'local',
        'status' => 'sent',
        'phone' => '6281234567890',
        'phone_normalized' => '6281234567890',
        'username' => 'VC123456',
        'customer_name' => 'Voucher VC123456',
    ]);
});

it('sends voucher code through ycloud when selected', function () {
    config()->set('services.ycloud_whatsapp.base_url', 'https://api.ycloud.com/v2');

    $owner = makeVoucherOwner();
    $profile = HotspotProfile::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Voucher 2 Jam',
        'profile_group_id' => null,
    ]);

    TenantSettings::getOrCreate($owner->id)->update([
        'wa_provider' => 'local',
        'ycloud_enabled' => true,
        'ycloud_api_key' => 'tenant-key-001',
        'ycloud_phone_number_id' => 'pn_tenant_001',
    ]);

    $voucher = Voucher::query()->create([
        'owner_id' => $owner->id,
        'hotspot_profile_id' => $profile->id,
        'profile_group_id' => null,
        'batch_name' => 'Batch Kilat',
        'code' => 'A7BC9D2E',
        'status' => 'unused',
        'username' => 'A7BC9D2E',
        'password' => 'A7BC9D2E',
    ]);

    Http::fake([
        'https://api.ycloud.com/v2/whatsapp/messages/sendDirectly' => Http::response([
            'whatsappMessage' => [
                'id' => 'wamid.voucher.ycloud.001',
                'status' => 'accepted',
            ],
        ], 200),
    ]);

    $this->actingAs($owner)
        ->postJson(route('vouchers.send-wa', $voucher), [
            'phone' => '081234567891',
            'provider' => 'ycloud',
        ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'Kode voucher berhasil dikirim ke 081234567891');

    Http::assertSent(function ($request) {
        $parameters = data_get($request->data(), 'template.components.0.parameters', []);

        return $request->url() === 'https://api.ycloud.com/v2/whatsapp/messages/sendDirectly'
            && $request->hasHeader('X-API-Key', 'tenant-key-001')
            && data_get($request->data(), 'from') === 'pn_tenant_001'
            && data_get($request->data(), 'to') === '6281234567891'
            && data_get($request->data(), 'template.name') === 'voucher_code_utility'
            && is_array($parameters)
            && str_contains((string) data_get($parameters, '0.text'), 'A7BC9D2E')
            && str_contains((string) data_get($parameters, '0.text'), 'Voucher 2 Jam');
    });

    $this->assertDatabaseHas('wa_blast_logs', [
        'owner_id' => $owner->id,
        'event' => 'voucher_code',
        'provider' => 'ycloud',
        'status' => 'sent',
        'phone' => '081234567891',
        'phone_normalized' => '6281234567891',
        'username' => 'A7BC9D2E',
        'customer_name' => 'Voucher A7BC9D2E',
        'provider_message_id' => 'wamid.voucher.ycloud.001',
        'template_name' => 'voucher_code_utility',
    ]);
});

it('rejects sending a used voucher to whatsapp', function () {
    $owner = makeVoucherOwner();
    $profile = HotspotProfile::factory()->create([
        'owner_id' => $owner->id,
        'profile_group_id' => null,
    ]);

    TenantSettings::getOrCreate($owner->id)->update([
        'wa_gateway_url' => 'https://gateway.example/wa',
        'wa_gateway_token' => 'device-token-voucher',
    ]);

    $voucher = Voucher::query()->create([
        'owner_id' => $owner->id,
        'hotspot_profile_id' => $profile->id,
        'profile_group_id' => null,
        'batch_name' => 'Batch Lama',
        'code' => 'USED1234',
        'status' => 'used',
        'username' => 'USED1234',
        'password' => 'USED1234',
        'used_at' => now(),
    ]);

    $this->actingAs($owner)
        ->postJson(route('vouchers.send-wa', $voucher), [
            'phone' => '081234567890',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error', 'Hanya voucher yang belum login yang bisa dikirim ke WhatsApp.');
});

it('forbids teknisi from sending voucher code to whatsapp', function () {
    $owner = makeVoucherOwner();
    $teknisi = User::factory()->create([
        'parent_id' => $owner->id,
        'role' => 'teknisi',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
    $profile = HotspotProfile::factory()->create([
        'owner_id' => $owner->id,
        'profile_group_id' => null,
    ]);

    $voucher = Voucher::query()->create([
        'owner_id' => $owner->id,
        'hotspot_profile_id' => $profile->id,
        'profile_group_id' => null,
        'batch_name' => 'Batch Teknisi',
        'code' => 'LOCK1234',
        'status' => 'unused',
        'username' => 'LOCK1234',
        'password' => 'LOCK1234',
    ]);

    $this->actingAs($teknisi)
        ->postJson(route('vouchers.send-wa', $voucher), [
            'phone' => '081234567890',
        ])
        ->assertForbidden();
});
