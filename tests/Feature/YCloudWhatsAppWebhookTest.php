<?php

use App\Models\Invoice;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Models\User;
use App\Models\WaChatMessage;
use App\Models\WaConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeYCloudWebhookTenant(): User
{
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    TenantSettings::getOrCreate($tenant->id)->update([
        'wa_provider' => 'ycloud',
        'ycloud_enabled' => true,
        'ycloud_api_key' => 'ycloud-key-001',
        'ycloud_phone_number_id' => 'pn_ycloud_001',
        'ycloud_business_number' => '62888111222333',
        'ycloud_webhook_secret' => 'ycloud-secret-001',
    ]);

    return $tenant;
}

function ycloudSignature(array $payload, string $secret, string $timestamp = '1710000000'): string
{
    $json = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $timestamp.'.'.$json, $secret);

    return 't='.$timestamp.',s='.$signature;
}

it('syncs inbound ycloud webhook into wa conversation and messages', function () {
    $tenant = makeYCloudWebhookTenant();

    $payload = [
        'id' => 'evt_ycloud_in_001',
        'type' => 'whatsapp.inbound_message.received',
        'whatsappInboundMessage' => [
            'id' => 'wamid.ycloud.inbound.001',
            'type' => 'text',
            'from' => '628111222333',
            'fromName' => 'Budi YCloud',
            'to' => [
                'phoneNumberId' => 'pn_ycloud_001',
            ],
            'text' => [
                'body' => 'Halo dari YCloud',
            ],
            'createTime' => now()->toIso8601String(),
        ],
    ];

    $this->postJson(
        route('ycloud.whatsapp.webhook.receive'),
        $payload,
        ['YCloud-Signature' => ycloudSignature($payload, 'ycloud-secret-001')]
    )->assertSuccessful()->assertJson(['status' => true]);

    $conversation = WaConversation::query()
        ->where('owner_id', $tenant->id)
        ->where('provider', 'ycloud')
        ->where('contact_phone', '628111222333')
        ->first();

    expect($conversation)->not->toBeNull();
    expect($conversation->contact_name)->toBe('Budi YCloud');
    expect($conversation->unread_count)->toBe(1);
    expect($conversation->last_message)->toBe('Halo dari YCloud');

    $message = WaChatMessage::query()->where('conversation_id', $conversation->id)->first();

    expect($message)->not->toBeNull();
    expect($message->provider)->toBe('ycloud');
    expect($message->direction)->toBe('inbound');
    expect($message->message_type)->toBe('text');
    expect($message->provider_message_id)->toBe('wamid.ycloud.inbound.001');

    $this->assertDatabaseHas('wa_webhook_logs', [
        'owner_id' => $tenant->id,
        'event_type' => 'ycloud_whatsapp.inbound_message.received',
        'session_id' => 'pn_ycloud_001',
        'sender' => '628111222333',
    ]);
});

it('updates ycloud message delivery status from webhook', function () {
    $tenant = makeYCloudWebhookTenant();

    $conversation = WaConversation::create([
        'owner_id' => $tenant->id,
        'provider' => 'ycloud',
        'contact_phone' => '628111222333',
        'contact_name' => 'Budi YCloud',
        'status' => 'open',
        'unread_count' => 0,
    ]);

    $message = WaChatMessage::create([
        'conversation_id' => $conversation->id,
        'owner_id' => $tenant->id,
        'provider' => 'ycloud',
        'direction' => 'outbound',
        'message' => 'Halo balik',
        'message_type' => 'text',
        'delivery_status' => 'accepted',
        'provider_message_id' => 'wamid.ycloud.outbound.001',
        'created_at' => now(),
    ]);

    $payload = [
        'id' => 'evt_ycloud_status_001',
        'type' => 'whatsapp.message.updated',
        'whatsappMessage' => [
            'id' => 'wamid.ycloud.outbound.001',
            'status' => 'delivered',
            'to' => [
                'phoneNumberId' => 'pn_ycloud_001',
            ],
            'pricing_analytics' => [
                'conversationCategory' => 'service',
            ],
        ],
    ];

    $this->postJson(
        route('ycloud.whatsapp.webhook.receive'),
        $payload,
        ['YCloud-Signature' => ycloudSignature($payload, 'ycloud-secret-001')]
    )->assertSuccessful();

    expect($message->fresh()->delivery_status)->toBe('delivered');
    expect($message->fresh()->pricing_metadata)->toBe([
        'conversationCategory' => 'service',
    ]);
});

it('downloads inbound ycloud image media during webhook sync', function () {
    Storage::fake('public');

    makeYCloudWebhookTenant();

    Http::fake([
        'https://media.ycloud.test/customer-proof.jpg' => Http::response('fake-image-content', 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $payload = [
        'id' => 'evt_ycloud_image_001',
        'type' => 'whatsapp.inbound_message.received',
        'whatsappInboundMessage' => [
            'id' => 'wamid.ycloud.image.001',
            'type' => 'image',
            'from' => '628111222334',
            'fromName' => 'Foto User',
            'to' => [
                'phoneNumberId' => 'pn_ycloud_001',
            ],
            'image' => [
                'id' => 'ycloud-media-001',
                'link' => 'https://media.ycloud.test/customer-proof.jpg',
                'mime_type' => 'image/jpeg',
                'filename' => 'customer-proof.jpg',
                'caption' => 'Bukti gangguan',
            ],
        ],
    ];

    $this->postJson(
        route('ycloud.whatsapp.webhook.receive'),
        $payload,
        ['YCloud-Signature' => ycloudSignature($payload, 'ycloud-secret-001')]
    )->assertSuccessful();

    $message = WaChatMessage::query()
        ->where('provider_message_id', 'wamid.ycloud.image.001')
        ->firstOrFail();

    expect($message->media_type)->toBe('image');
    expect($message->media_path)->not->toBeNull();
    Storage::disk('public')->assertExists($message->media_path);
});

it('sends ycloud auto reply using existing bot flow', function () {
    Http::fake([
        'https://api.ycloud.com/v2/whatsapp/messages/sendDirectly' => Http::response([
            'whatsappMessage' => [
                'id' => 'wamid.ycloud.auto-reply.001',
                'status' => 'accepted',
            ],
        ], 200),
    ]);

    $tenant = makeYCloudWebhookTenant();

    $settings = TenantSettings::query()->where('user_id', $tenant->id)->firstOrFail();
    $settings->update([
        'business_name' => 'Net YCloud',
    ]);

    $pppUser = PppUser::create([
        'owner_id' => $tenant->id,
        'customer_id' => 'CUST-YC-001',
        'customer_name' => 'Budi YCloud',
        'nomor_hp' => '628111222333',
        'username' => 'budi-ycloud',
    ]);

    Invoice::create([
        'invoice_number' => 'INV-YCLOUD-001',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $tenant->id,
        'customer_id' => 'CUST-YC-001',
        'customer_name' => 'Budi YCloud',
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket 20M',
        'total' => 175000,
        'due_date' => now()->addDays(5)->toDateString(),
        'status' => 'unpaid',
        'payment_token' => 'payment-token-ycloud-001',
    ]);

    $payload = [
        'id' => 'evt_ycloud_bot_001',
        'type' => 'whatsapp.inbound_message.received',
        'whatsappInboundMessage' => [
            'id' => 'wamid.ycloud.inbound.bot.001',
            'type' => 'text',
            'from' => '628111222333',
            'fromName' => 'Budi YCloud',
            'to' => [
                'phoneNumberId' => 'pn_ycloud_001',
            ],
            'text' => [
                'body' => 'halo cek tagihan saya',
            ],
        ],
    ];

    $this->postJson(
        route('ycloud.whatsapp.webhook.receive'),
        $payload,
        ['YCloud-Signature' => ycloudSignature($payload, 'ycloud-secret-001')]
    )->assertSuccessful();

    Http::assertSent(function ($request) {
        $body = $request->data();
        $message = (string) data_get($body, 'text.body', '');

        return $request->url() === 'https://api.ycloud.com/v2/whatsapp/messages/sendDirectly'
            && data_get($body, 'from') === 'pn_ycloud_001'
            && data_get($body, 'to') === '628111222333'
            && str_contains($message, 'INV-YCLOUD-001')
            && str_contains($message, 'Rp 175.000');
    });
});
