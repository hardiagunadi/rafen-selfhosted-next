<?php

use App\Models\Invoice;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('sends manual paid invoice notification through ycloud for ycloud tenants', function () {
    config()->set('services.ycloud_whatsapp.base_url', 'https://api.ycloud.com/v2');

    $owner = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    TenantSettings::getOrCreate($owner->id)->update([
        'wa_provider' => 'ycloud',
        'ycloud_enabled' => true,
        'ycloud_api_key' => 'tenant-key-001',
        'ycloud_phone_number_id' => 'pn_tenant_001',
        'wa_notify_payment' => false,
    ]);

    $pppUser = PppUser::factory()->forOwner($owner)->create([
        'customer_name' => 'Diah',
        'customer_id' => '2153064650',
        'nomor_hp' => '085363319219',
        'username' => 'diah@tmd.id',
        'tipe_service' => 'pppoe',
    ]);

    $invoice = Invoice::query()->create([
        'invoice_number' => 'INV-TEST-YCLOUD-001',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $owner->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Ultra Fiber',
        'total' => 220000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    Http::fake([
        'https://api.ycloud.com/v2/whatsapp/messages/sendDirectly' => Http::response([
            'whatsappMessage' => [
                'id' => 'wamid.invoice.paid.001',
                'status' => 'accepted',
            ],
        ], 200),
    ]);

    $this->actingAs($owner)
        ->postJson(route('invoices.send-wa', $invoice))
        ->assertSuccessful()
        ->assertJsonPath('status', 'Notifikasi WhatsApp berhasil dikirim ke 085363319219');

    Http::assertSent(function ($request) {
        $parameters = data_get($request->data(), 'template.components.0.parameters', []);

        return $request->url() === 'https://api.ycloud.com/v2/whatsapp/messages/sendDirectly'
            && $request->hasHeader('X-API-Key', 'tenant-key-001')
            && data_get($request->data(), 'from') === 'pn_tenant_001'
            && data_get($request->data(), 'to') === '6285363319219'
            && data_get($request->data(), 'template.name') === 'invoice_paid_utility'
            && is_array($parameters)
            && data_get($parameters, '0.text') === 'Diah'
            && data_get($parameters, '1.text') === 'INV-TEST-YCLOUD-001'
            && data_get($parameters, '2.text') === '2153064650';
    });

    $this->assertDatabaseHas('wa_blast_logs', [
        'owner_id' => $owner->id,
        'event' => 'invoice_paid',
        'provider' => 'ycloud',
        'status' => 'sent',
        'invoice_id' => $invoice->id,
        'invoice_number' => 'INV-TEST-YCLOUD-001',
        'phone' => '085363319219',
        'phone_normalized' => '6285363319219',
        'provider_message_id' => 'wamid.invoice.paid.001',
        'template_name' => 'invoice_paid_utility',
    ]);
});

it('can force local gateway when sending invoice notification from a ycloud tenant', function () {
    config()->set('wa.multi_session.public_url', '');
    config()->set('wa.multi_session.auth_token', '');
    config()->set('wa.multi_session.master_key', '');
    config()->set('services.ycloud_whatsapp.base_url', 'https://api.ycloud.com/v2');

    $owner = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    TenantSettings::getOrCreate($owner->id)->update([
        'wa_provider' => 'ycloud',
        'wa_gateway_url' => 'https://gateway.example/wa',
        'wa_gateway_token' => 'local-token-001',
        'wa_gateway_key' => 'local-master-key',
        'wa_msg_randomize' => false,
        'wa_antispam_enabled' => false,
        'ycloud_enabled' => true,
        'ycloud_api_key' => 'tenant-key-001',
        'ycloud_phone_number_id' => 'pn_tenant_001',
    ]);

    $pppUser = PppUser::factory()->forOwner($owner)->create([
        'customer_name' => 'Budi',
        'customer_id' => '2153000002',
        'nomor_hp' => '081234567899',
        'username' => 'budi@tmd.id',
        'tipe_service' => 'pppoe',
    ]);

    $invoice = Invoice::query()->create([
        'invoice_number' => 'INV-TEST-LOCAL-001',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $owner->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Hemat',
        'total' => 150000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    Http::fake([
        'https://gateway.example/wa/api/v2/send-message' => Http::response([
            'status' => true,
            'data' => [
                'messages' => [
                    ['status' => 'queued', 'ref_id' => 'local-ref-001'],
                ],
            ],
        ], 200),
        'https://api.ycloud.com/v2/whatsapp/messages/sendDirectly' => Http::response([
            'whatsappMessage' => [
                'id' => 'wamid.should.not.send',
                'status' => 'accepted',
            ],
        ], 200),
    ]);

    $this->actingAs($owner)
        ->postJson(route('invoices.send-wa', $invoice), [
            'provider' => 'local',
        ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'Notifikasi WhatsApp berhasil dikirim ke 081234567899');

    Http::assertSent(fn ($request) => $request->url() === 'https://gateway.example/wa/api/v2/send-message');
    Http::assertNotSent(fn ($request) => $request->url() === 'https://api.ycloud.com/v2/whatsapp/messages/sendDirectly');

    $this->assertDatabaseHas('wa_blast_logs', [
        'owner_id' => $owner->id,
        'event' => 'invoice_paid',
        'provider' => 'local',
        'status' => 'sent',
        'invoice_id' => $invoice->id,
        'phone' => '6281234567899',
    ]);
});
