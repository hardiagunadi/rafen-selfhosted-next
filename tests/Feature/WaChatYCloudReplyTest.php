<?php

use App\Models\TenantSettings;
use App\Models\User;
use App\Models\WaChatMessage;
use App\Models\WaConversation;
use App\Models\WaWebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeYCloudChatTenant(): User
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
    ]);

    return $tenant;
}

function makeYCloudConversation(int $ownerId): WaConversation
{
    return WaConversation::create([
        'owner_id' => $ownerId,
        'provider' => 'ycloud',
        'session_id' => 'pn_ycloud_001',
        'contact_phone' => '628111222333',
        'contact_name' => 'Budi YCloud',
        'status' => 'open',
        'unread_count' => 1,
        'service_window_expires_at' => now()->addHours(12),
    ]);
}

it('marks ycloud conversation as read when opened in inbox', function () {
    $tenant = makeYCloudChatTenant();
    $conversation = makeYCloudConversation($tenant->id);

    WaChatMessage::create([
        'conversation_id' => $conversation->id,
        'owner_id' => $tenant->id,
        'provider' => 'ycloud',
        'direction' => 'inbound',
        'message' => 'Halo masuk',
        'message_type' => 'text',
        'provider_message_id' => 'wamid.inbound.001',
        'created_at' => now(),
    ]);

    Http::fake([
        'https://api.ycloud.com/v2/whatsapp/inboundMessages/*/markAsRead' => Http::response([], 200),
    ]);

    $this->actingAs($tenant)
        ->getJson(route('wa-chat.show', $conversation))
        ->assertSuccessful()
        ->assertJsonPath('conversation.provider', 'ycloud');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.ycloud.com/v2/whatsapp/inboundMessages/wamid.inbound.001/markAsRead'
            && $request->hasHeader('X-API-Key', 'ycloud-key-001');
    });
});

it('replies to ycloud conversations using ycloud direct message api', function () {
    $tenant = makeYCloudChatTenant();
    $conversation = makeYCloudConversation($tenant->id);

    Http::fake([
        'https://api.ycloud.com/v2/whatsapp/messages/sendDirectly' => Http::response([
            'whatsappMessage' => [
                'id' => 'wamid.outbound.001',
                'status' => 'accepted',
            ],
        ], 200),
    ]);

    $this->actingAs($tenant)
        ->postJson(route('wa-chat.reply', $conversation), [
            'message' => 'Siap kami bantu',
        ])
        ->assertSuccessful()
        ->assertJson(['success' => true]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.ycloud.com/v2/whatsapp/messages/sendDirectly'
            && $request->hasHeader('X-API-Key', 'ycloud-key-001')
            && data_get($request->data(), 'from') === 'pn_ycloud_001'
            && data_get($request->data(), 'to') === '628111222333'
            && data_get($request->data(), 'type') === 'text';
    });

    $this->assertDatabaseHas('wa_chat_messages', [
        'conversation_id' => $conversation->id,
        'provider' => 'ycloud',
        'direction' => 'outbound',
        'message_type' => 'text',
        'provider_message_id' => 'wamid.outbound.001',
    ]);
});

it('sends image replies for ycloud conversations', function () {
    Storage::fake('public');

    $tenant = makeYCloudChatTenant();
    $conversation = makeYCloudConversation($tenant->id);

    Http::fake([
        'https://api.ycloud.com/v2/whatsapp/messages/sendDirectly' => Http::response([
            'whatsappMessage' => [
                'id' => 'wamid.outbound.image.001',
                'status' => 'accepted',
            ],
        ], 200),
    ]);

    $this->actingAs($tenant)
        ->post(route('wa-chat.reply-image', $conversation), [
            'image' => UploadedFile::fake()->image('reply.png'),
            'caption' => 'Lampiran untuk Anda',
        ])
        ->assertSuccessful()
        ->assertJson(['success' => true]);

    Http::assertSent(function ($request) {
        $imageLink = (string) data_get($request->data(), 'image.link', '');

        return $request->url() === 'https://api.ycloud.com/v2/whatsapp/messages/sendDirectly'
            && data_get($request->data(), 'type') === 'image'
            && data_get($request->data(), 'image.caption') !== null
            && str_contains($imageLink, '/storage/wa-chat-images/');
    });

    $this->assertDatabaseHas('wa_chat_messages', [
        'conversation_id' => $conversation->id,
        'provider' => 'ycloud',
        'direction' => 'outbound',
        'message_type' => 'image',
        'media_type' => 'image',
        'provider_message_id' => 'wamid.outbound.image.001',
    ]);
});

it('hydrates and serves ycloud inbound media on demand from chat media route', function () {
    Storage::fake('public');

    $tenant = makeYCloudChatTenant();
    $conversation = makeYCloudConversation($tenant->id);

    $message = WaChatMessage::create([
        'conversation_id' => $conversation->id,
        'owner_id' => $tenant->id,
        'provider' => 'ycloud',
        'direction' => 'inbound',
        'message' => '[image]',
        'message_type' => 'image',
        'media_type' => 'image',
        'provider_message_id' => 'wamid.inbound.media.001',
        'created_at' => now(),
    ]);

    WaWebhookLog::create([
        'owner_id' => $tenant->id,
        'event_type' => 'ycloud_whatsapp.inbound_message.received',
        'session_id' => 'pn_ycloud_001',
        'sender' => '628111222333',
        'message' => 'wamid.inbound.media.001',
        'status' => 'image',
        'payload' => [
            'whatsappInboundMessage' => [
                'id' => 'wamid.inbound.media.001',
                'type' => 'image',
                'from' => '628111222333',
                'image' => [
                    'id' => 'ycloud-media-ondemand-001',
                    'link' => 'https://media.ycloud.test/ondemand.jpg',
                    'mime_type' => 'image/jpeg',
                    'filename' => 'ondemand.jpg',
                ],
            ],
        ],
    ]);

    Http::fake([
        'https://media.ycloud.test/ondemand.jpg' => Http::response('ondemand-image', 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $this->actingAs($tenant)
        ->get(route('wa-chat.media', $message))
        ->assertSuccessful();

    $freshMessage = $message->fresh();

    expect($freshMessage->media_path)->not->toBeNull();
    Storage::disk('public')->assertExists($freshMessage->media_path);
});
