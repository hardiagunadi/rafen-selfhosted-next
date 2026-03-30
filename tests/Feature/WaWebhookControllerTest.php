<?php

use App\Models\Invoice;
use App\Models\MikrotikConnection;
use App\Models\PppUser;
use App\Models\RadiusAccount;
use App\Models\TenantSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    Log::spy();
    config()->set('wa.multi_session.public_url', '');
    config()->set('wa.multi_session.auth_token', '');
    config()->set('wa.multi_session.master_key', '');
});

it('accepts standard webhook message endpoint and stores sender with status', function () {
    $response = $this->postJson('/webhook/message', [
        'session' => 'device-01',
        'from' => '6281211112222@s.whatsapp.net',
        'message' => 'Halo dari pelanggan',
        'message_status' => 'received',
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => true]);

    $this->assertDatabaseHas('wa_webhook_logs', [
        'event_type' => 'message',
        'session_id' => 'device-01',
        'sender' => '6281211112222',
        'message' => 'Halo dari pelanggan',
        'status' => 'received',
    ]);
});

it('accepts wa-prefixed webhook message endpoint', function () {
    $response = $this->postJson('/webhook/wa/message', [
        'session' => 'device-02',
        'sender' => '6281399990000@s.whatsapp.net',
        'message' => [
            'text' => 'Tes format array',
        ],
        'status' => 'received',
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => true]);

    $this->assertDatabaseHas('wa_webhook_logs', [
        'event_type' => 'message',
        'session_id' => 'device-02',
        'sender' => '6281399990000',
        'message' => 'Tes format array',
        'status' => 'received',
    ]);
});

it('accepts wa-prefixed auto-reply endpoint', function () {
    $response = $this->postJson('/webhook/wa/auto-reply', [
        'session' => 'device-02',
        'from' => '6281388887777@s.whatsapp.net',
        'message' => 'Tes auto reply',
        'message_status' => 'received',
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => true]);

    $this->assertDatabaseHas('wa_webhook_logs', [
        'event_type' => 'auto_reply',
        'session_id' => 'device-02',
        'sender' => '6281388887777',
        'message' => 'Tes auto reply',
        'status' => 'received',
    ]);
});

it('accepts wa-prefixed status endpoint', function () {
    $response = $this->postJson('/webhook/wa/status', [
        'session' => 'device-04',
        'message_id' => 'BAE5F123',
        'message_status' => 'READ',
        'tracking_url' => '/message/status?session=device-04&id=BAE5F123',
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => true]);

    $this->assertDatabaseHas('wa_webhook_logs', [
        'event_type' => 'status',
        'session_id' => 'device-04',
        'message' => 'BAE5F123',
        'status' => 'READ',
    ]);
});

it('accepts wa root endpoint and auto-detects message event', function () {
    $response = $this->postJson('/webhook/wa', [
        'session' => 'device-root-01',
        'sender' => '628121000111@s.whatsapp.net',
        'message' => 'Pesan masuk root endpoint',
        'message_status' => 'received',
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => true]);

    $this->assertDatabaseHas('wa_webhook_logs', [
        'event_type' => 'message',
        'session_id' => 'device-root-01',
        'sender' => '628121000111',
        'message' => 'Pesan masuk root endpoint',
        'status' => 'received',
    ]);
});

it('accepts wa root endpoint and auto-detects session event', function () {
    $response = $this->postJson('/webhook/wa', [
        'session' => 'device-root-02',
        'status' => 'online',
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => true]);

    $this->assertDatabaseHas('wa_webhook_logs', [
        'event_type' => 'session',
        'session_id' => 'device-root-02',
        'status' => 'online',
    ]);
});

it('assigns owner id when tenant webhook path uses valid secret', function () {
    $tenant = User::factory()->create();
    $settings = TenantSettings::getOrCreate($tenant->id);
    $settings->update([
        'wa_webhook_secret' => 'tenantsecret123',
    ]);

    $response = $this->postJson('/webhook/wa/'.$tenant->id.'/tenantsecret123/message', [
        'session' => 'tenant-device-01',
        'sender' => '6281111222333@s.whatsapp.net',
        'message' => 'Pesan tenant valid',
        'message_status' => 'received',
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => true]);

    $this->assertDatabaseHas('wa_webhook_logs', [
        'owner_id' => $tenant->id,
        'event_type' => 'message',
        'session_id' => 'tenant-device-01',
        'sender' => '6281111222333',
        'message' => 'Pesan tenant valid',
        'status' => 'received',
    ]);
});

it('keeps owner id null when tenant webhook path uses wrong secret', function () {
    $tenant = User::factory()->create();
    $settings = TenantSettings::getOrCreate($tenant->id);
    $settings->update([
        'wa_webhook_secret' => 'tenantsecret123',
    ]);

    $response = $this->postJson('/webhook/wa/'.$tenant->id.'/wrongsecret/message', [
        'session' => 'tenant-device-02',
        'sender' => '6281444555666@s.whatsapp.net',
        'message' => 'Pesan tenant secret salah',
        'message_status' => 'received',
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => true]);

    $this->assertDatabaseHas('wa_webhook_logs', [
        'owner_id' => null,
        'event_type' => 'message',
        'session_id' => 'tenant-device-02',
        'sender' => '6281444555666',
        'message' => 'Pesan tenant secret salah',
        'status' => 'received',
    ]);
});

it('accepts standard webhook session endpoint', function () {
    $response = $this->postJson('/webhook/session', [
        'session_id' => 'device-03',
        'status' => 'online',
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => true]);

    $this->assertDatabaseHas('wa_webhook_logs', [
        'event_type' => 'session',
        'session_id' => 'device-03',
        'status' => 'online',
    ]);
});

it('sends conversational auto-reply for invoice intent', function () {
    Http::fake([
        'https://gw.example/api/v2/send-message' => Http::response([
            'status' => true,
            'data' => [
                'messages' => [
                    [
                        'status' => 'queued',
                        'ref_id' => 'auto-reply-ref-001',
                    ],
                ],
            ],
        ], 200),
    ]);

    $tenant = User::factory()->create();
    $settings = TenantSettings::getOrCreate($tenant->id);
    $settings->update([
        'business_name' => 'Net Watu',
        'business_phone' => '081234567890',
        'wa_gateway_url' => 'https://gw.example',
        'wa_gateway_token' => 'device-token-abc',
        'wa_gateway_key' => 'master-key-xyz',
        'wa_webhook_secret' => 'tenant-secret-001',
    ]);

    $pppUser = PppUser::create([
        'owner_id' => $tenant->id,
        'customer_id' => 'CUST-001',
        'customer_name' => 'Budi',
        'nomor_hp' => '628111222333',
        'username' => 'budi-net',
    ]);

    Invoice::create([
        'invoice_number' => 'INV-TEST-001',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $tenant->id,
        'customer_id' => 'CUST-001',
        'customer_name' => 'Budi',
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket 20M',
        'total' => 150000,
        'due_date' => now()->addDays(5)->toDateString(),
        'status' => 'unpaid',
        'payment_token' => 'token-invoice-001',
    ]);

    $response = $this->postJson('/webhook/wa/'.$tenant->id.'/tenant-secret-001/message', [
        'session' => 'tenant-device-chat',
        'sender' => '628111222333@s.whatsapp.net',
        'message' => 'Halo, cek tagihan saya',
        'message_status' => 'received',
    ]);

    $response->assertSuccessful()->assertJson(['status' => true]);

    Http::assertSentCount(1);
    Http::assertSent(function ($request) {
        $body = $request->data();
        $message = data_get($body, 'data.0.message', '');

        return str_contains($request->url(), '/api/v2/send-message')
            && str_contains($message, 'INV-TEST-001')
            && str_contains($message, 'Rp 150.000');
    });

    $this->assertDatabaseHas('wa_blast_logs', [
        'event' => 'auto_reply_outbound',
        'status' => 'sent',
        'phone' => '628111222333',
    ]);
});

it('does not send conversational auto-reply for fromMe or group payload', function () {
    Http::fake();

    $tenant = User::factory()->create();
    $settings = TenantSettings::getOrCreate($tenant->id);
    $settings->update([
        'wa_gateway_url' => 'https://gw.example',
        'wa_gateway_token' => 'device-token-abc',
        'wa_webhook_secret' => 'tenant-secret-002',
    ]);

    $responseFromMe = $this->postJson('/webhook/wa/'.$tenant->id.'/tenant-secret-002/message', [
        'session' => 'tenant-device-chat',
        'sender' => '628111222333@s.whatsapp.net',
        'message' => 'cek tagihan',
        'fromMe' => true,
    ]);

    $responseGroup = $this->postJson('/webhook/wa/'.$tenant->id.'/tenant-secret-002/message', [
        'session' => 'tenant-device-chat',
        'sender' => '628777888999@g.us',
        'message' => 'cek tagihan',
        'isGroup' => true,
    ]);

    $responseFromMe->assertSuccessful()->assertJson(['status' => true]);
    $responseGroup->assertSuccessful()->assertJson(['status' => true]);

    Http::assertNothingSent();
});

it('sends network status reply based on active radius session', function () {
    Http::fake([
        'https://gw.example/api/v2/send-message' => Http::response([
            'status' => true,
            'data' => [
                'messages' => [
                    [
                        'status' => 'queued',
                        'ref_id' => 'auto-reply-ref-002',
                    ],
                ],
            ],
        ], 200),
    ]);

    $tenant = User::factory()->create();
    $settings = TenantSettings::getOrCreate($tenant->id);
    $settings->update([
        'business_name' => 'Net Watu',
        'wa_gateway_url' => 'https://gw.example',
        'wa_gateway_token' => 'device-token-abc',
        'wa_webhook_secret' => 'tenant-secret-003',
    ]);

    $pppUser = PppUser::create([
        'owner_id' => $tenant->id,
        'customer_id' => 'CUST-002',
        'customer_name' => 'Rina',
        'nomor_hp' => '628111333444',
        'username' => 'rina-net',
        'status_akun' => 'enable',
    ]);

    $connection = MikrotikConnection::factory()->create([
        'owner_id' => $tenant->id,
    ]);

    RadiusAccount::create([
        'mikrotik_connection_id' => $connection->id,
        'username' => $pppUser->username,
        'password' => 'secret',
        'service' => 'pppoe',
        'is_active' => true,
        'uptime' => '01:05:10',
    ]);

    $response = $this->postJson('/webhook/wa/'.$tenant->id.'/tenant-secret-003/message', [
        'session' => 'tenant-device-chat',
        'sender' => '628111333444@s.whatsapp.net',
        'message' => 'status gangguan internet saya',
        'message_status' => 'received',
    ]);

    $response->assertSuccessful()->assertJson(['status' => true]);

    Http::assertSent(function ($request) {
        $body = $request->data();
        $message = data_get($body, 'data.0.message', '');

        return str_contains($message, 'terpantau *ONLINE*')
            && str_contains($message, 'uptime 01:05:10');
    });
});

it('sends payment format guidance when asked', function () {
    Http::fake([
        'https://gw.example/api/v2/send-message' => Http::response([
            'status' => true,
            'data' => [
                'messages' => [
                    [
                        'status' => 'queued',
                        'ref_id' => 'auto-reply-ref-003',
                    ],
                ],
            ],
        ], 200),
    ]);

    $tenant = User::factory()->create();
    $settings = TenantSettings::getOrCreate($tenant->id);
    $settings->update([
        'wa_gateway_url' => 'https://gw.example',
        'wa_gateway_token' => 'device-token-abc',
        'wa_webhook_secret' => 'tenant-secret-004',
    ]);

    $response = $this->postJson('/webhook/wa/'.$tenant->id.'/tenant-secret-004/message', [
        'session' => 'tenant-device-chat',
        'sender' => '628111555666@s.whatsapp.net',
        'message' => 'boleh minta format bukti transfer?',
        'message_status' => 'received',
    ]);

    $response->assertSuccessful()->assertJson(['status' => true]);

    Http::assertSent(function ($request) {
        $body = $request->data();
        $message = data_get($body, 'data.0.message', '');

        return str_contains($message, 'format konfirmasi pembayaran')
            && str_contains($message, 'Lampiran: foto/screenshot bukti transfer');
    });
});

it('suppresses duplicate auto-reply for same message within cooldown window', function () {
    Http::fake([
        'https://gw.example/api/v2/send-message' => Http::response([
            'status' => true,
            'data' => [
                'messages' => [
                    [
                        'status' => 'queued',
                        'ref_id' => 'auto-reply-ref-004',
                    ],
                ],
            ],
        ], 200),
    ]);

    $tenant = User::factory()->create();
    $settings = TenantSettings::getOrCreate($tenant->id);
    $settings->update([
        'wa_gateway_url' => 'https://gw.example',
        'wa_gateway_token' => 'device-token-abc',
        'wa_webhook_secret' => 'tenant-secret-005',
    ]);

    $payload = [
        'session' => 'tenant-device-chat',
        'sender' => '628111777888@s.whatsapp.net',
        'message' => 'halo',
        'message_status' => 'received',
    ];

    $responseOne = $this->postJson('/webhook/wa/'.$tenant->id.'/tenant-secret-005/message', $payload);
    $responseTwo = $this->postJson('/webhook/wa/'.$tenant->id.'/tenant-secret-005/message', $payload);

    $responseOne->assertSuccessful()->assertJson(['status' => true]);
    $responseTwo->assertSuccessful()->assertJson(['status' => true]);

    Http::assertSentCount(1);
});
