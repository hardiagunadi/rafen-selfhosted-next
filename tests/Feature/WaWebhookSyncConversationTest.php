<?php

use App\Models\TenantSettings;
use App\Models\User;
use App\Models\WaChatMessage;
use App\Models\WaConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function webhookTenant(): array
{
    $user = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
    $settings = TenantSettings::getOrCreate($user->id);
    $settings->update([
        'wa_webhook_secret' => 'testsecret',
        'wa_gateway_url' => 'http://localhost:3000',
        'wa_gateway_token' => 'testtoken',
    ]);

    return [$user, $settings];
}

// ── syncToConversation: creates conversation ───────────────────────────────────

it('creates wa_conversation and wa_chat_message from inbound webhook', function () {
    [$tenant] = webhookTenant();

    $payload = [
        'session' => 'session-1',
        'id' => 'local-msg-001',
        'sender' => '6281234567890@s.whatsapp.net',
        'pushName' => 'Budi',
        'message' => 'Halo internet saya mati',
        'fromMe' => false,
        'isGroup' => false,
        'message_status' => 'received',
    ];

    $this->postJson(route('wa.webhook.ingest.tenant.compat', [
        'tenant' => $tenant->id,
        'secret' => 'testsecret',
    ]), $payload)->assertOk();

    $conv = WaConversation::where('owner_id', $tenant->id)
        ->where('contact_phone', '6281234567890')
        ->first();

    expect($conv)->not->toBeNull();
    expect($conv->provider)->toBe('local');
    expect($conv->contact_name)->toBe('Budi');
    expect($conv->status)->toBe('open');
    expect($conv->unread_count)->toBe(1);
    expect($conv->last_message)->toBe('Halo internet saya mati');

    $msg = WaChatMessage::where('conversation_id', $conv->id)->first();
    expect($msg)->not->toBeNull();
    expect($msg->provider)->toBe('local');
    expect($msg->direction)->toBe('inbound');
    expect($msg->message)->toBe('Halo internet saya mati');
    expect($msg->message_type)->toBe('text');
    expect($msg->provider_message_id)->toBe('local-msg-001');
});

// ── syncToConversation: increments unread on same conversation ─────────────────

it('increments unread_count on subsequent inbound messages', function () {
    [$tenant] = webhookTenant();

    $basePayload = [
        'session' => 'session-1',
        'sender' => '6281111111111@s.whatsapp.net',
        'fromMe' => false,
        'isGroup' => false,
        'message' => 'pesan 1',
    ];

    $this->postJson(route('wa.webhook.ingest.tenant.compat', [
        'tenant' => $tenant->id, 'secret' => 'testsecret',
    ]), $basePayload)->assertOk();

    $this->postJson(route('wa.webhook.ingest.tenant.compat', [
        'tenant' => $tenant->id, 'secret' => 'testsecret',
    ]), array_merge($basePayload, ['message' => 'pesan 2']))->assertOk();

    $conv = WaConversation::where('owner_id', $tenant->id)
        ->where('contact_phone', '6281111111111')
        ->first();

    expect($conv->unread_count)->toBe(2);
    expect(WaChatMessage::where('conversation_id', $conv->id)->count())->toBe(2);
});

// ── syncToConversation: skips group messages ───────────────────────────────────

it('does not create conversation for group messages', function () {
    [$tenant] = webhookTenant();

    $payload = [
        'session' => 'session-1',
        'sender' => '6281234567890-1620000000@g.us',
        'fromMe' => false,
        'isGroup' => true,
        'message' => 'Pesan grup',
    ];

    $this->postJson(route('wa.webhook.ingest.tenant.compat', [
        'tenant' => $tenant->id, 'secret' => 'testsecret',
    ]), $payload)->assertOk();

    expect(WaConversation::where('owner_id', $tenant->id)->count())->toBe(0);
});

// ── syncToConversation: skips outbound (fromMe) ───────────────────────────────

it('does not create conversation for outbound messages', function () {
    [$tenant] = webhookTenant();

    $payload = [
        'session' => 'session-1',
        'sender' => '6281234567890@s.whatsapp.net',
        'fromMe' => true,
        'isGroup' => false,
        'message' => 'Pesan dari bot',
    ];

    $this->postJson(route('wa.webhook.ingest.tenant.compat', [
        'tenant' => $tenant->id, 'secret' => 'testsecret',
    ]), $payload)->assertOk();

    expect(WaConversation::where('owner_id', $tenant->id)->count())->toBe(0);
});

// ── syncToConversation: reuses existing conversation ──────────────────────────

it('reuses existing conversation for same phone', function () {
    [$tenant] = webhookTenant();

    $existing = WaConversation::create([
        'owner_id' => $tenant->id,
        'contact_phone' => '6282222222222',
        'status' => 'resolved',
        'unread_count' => 0,
    ]);

    $payload = [
        'session' => 'session-1',
        'sender' => '6282222222222@s.whatsapp.net',
        'fromMe' => false,
        'isGroup' => false,
        'message' => 'Masalah lagi',
    ];

    $this->postJson(route('wa.webhook.ingest.tenant.compat', [
        'tenant' => $tenant->id, 'secret' => 'testsecret',
    ]), $payload)->assertOk();

    // Should NOT create a new conversation
    expect(WaConversation::where('owner_id', $tenant->id)
        ->where('contact_phone', '6282222222222')
        ->count())->toBe(1);

    // Should reopen resolved conversation
    expect($existing->fresh()->status)->toBe('open');
    expect($existing->fresh()->unread_count)->toBe(1);
});

// ── Webhook always returns 200 even on sync error ─────────────────────────────

it('always returns 200 even if sync fails', function () {
    [$tenant] = webhookTenant();

    // Empty/malformed payload — sync should silently fail, webhook still 200
    $this->postJson(route('wa.webhook.ingest.tenant.compat', [
        'tenant' => $tenant->id, 'secret' => 'testsecret',
    ]), ['fromMe' => false, 'isGroup' => false, 'sender' => '', 'message' => ''])
        ->assertOk();
});
