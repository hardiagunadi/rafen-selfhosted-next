<?php

use App\Models\TenantSettings;
use App\Models\User;
use App\Models\WaChatMessage;
use App\Models\WaConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helper ────────────────────────────────────────────────────────────────────

function makeTenant(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ], $attrs));
}

function makeSubUser(int $parentId, string $role): User
{
    return User::factory()->create([
        'parent_id' => $parentId,
        'role' => $role,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

function makeConversation(int $ownerId, array $attrs = []): WaConversation
{
    return WaConversation::create(array_merge([
        'owner_id' => $ownerId,
        'contact_phone' => '628123456789',
        'contact_name' => 'Pelanggan Test',
        'status' => 'open',
        'unread_count' => 0,
    ], $attrs));
}

// ── Chat WA: index ─────────────────────────────────────────────────────────────

it('allows cs role to access wa-chat index', function () {
    $tenant = makeTenant();
    $cs = makeSubUser($tenant->id, 'cs');

    $this->actingAs($cs)->get(route('wa-chat.index'))->assertOk();
});

it('blocks teknisi from accessing wa-chat index', function () {
    $tenant = makeTenant();
    $teknisi = makeSubUser($tenant->id, 'teknisi');

    $this->actingAs($teknisi)->get(route('wa-chat.index'))->assertForbidden();
});

it('blocks keuangan from accessing wa-chat index', function () {
    $tenant = makeTenant();
    $keuangan = makeSubUser($tenant->id, 'keuangan');

    $this->actingAs($keuangan)->get(route('wa-chat.index'))->assertForbidden();
});

// ── Chat WA: conversations JSON ────────────────────────────────────────────────

it('returns conversations belonging to tenant', function () {
    $tenant = makeTenant();
    $other = makeTenant();

    $mine = makeConversation($tenant->id, ['unread_count' => 2]);
    $notMine = makeConversation($other->id);

    $res = $this->actingAs($tenant)
        ->getJson(route('wa-chat.conversations'))
        ->assertOk()
        ->json('data');

    $ids = collect($res)->pluck('id')->all();
    expect($ids)->toContain($mine->id)->not->toContain($notMine->id);
});

it('filters conversations by status', function () {
    $tenant = makeTenant();

    makeConversation($tenant->id, ['status' => 'open']);
    makeConversation($tenant->id, ['status' => 'resolved', 'contact_phone' => '628000000001']);

    $res = $this->actingAs($tenant)
        ->getJson(route('wa-chat.conversations', ['status' => 'resolved']))
        ->assertOk()
        ->json('data');

    expect(collect($res)->pluck('status')->unique()->all())->toEqual(['resolved']);
});

// ── Chat WA: show (messages) ────────────────────────────────────────────────────

it('returns messages for own conversation and resets unread_count', function () {
    $tenant = makeTenant();
    $conv = makeConversation($tenant->id, ['unread_count' => 3]);

    WaChatMessage::create([
        'conversation_id' => $conv->id,
        'owner_id' => $tenant->id,
        'direction' => 'inbound',
        'message' => 'Halo test',
        'created_at' => now(),
    ]);

    $res = $this->actingAs($tenant)
        ->getJson(route('wa-chat.show', $conv))
        ->assertOk();

    expect($res->json('messages'))->toHaveCount(1);
    expect($conv->fresh()->unread_count)->toBe(0);
});

it('prevents accessing another tenant conversation', function () {
    $tenant = makeTenant();
    $other = makeTenant();
    $conv = makeConversation($other->id);

    $this->actingAs($tenant)
        ->getJson(route('wa-chat.show', $conv))
        ->assertForbidden();
});

// ── Chat WA: markResolved / markOpen ──────────────────────────────────────────

it('can mark conversation as resolved', function () {
    $tenant = makeTenant();
    $conv = makeConversation($tenant->id, ['status' => 'open']);

    $this->actingAs($tenant)
        ->postJson(route('wa-chat.resolve', $conv), ['_token' => csrf_token()])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($conv->fresh()->status)->toBe('resolved');
});

it('can reopen a resolved conversation', function () {
    $tenant = makeTenant();
    $conv = makeConversation($tenant->id, ['status' => 'resolved']);

    $this->actingAs($tenant)
        ->postJson(route('wa-chat.open', $conv), ['_token' => csrf_token()])
        ->assertOk();

    expect($conv->fresh()->status)->toBe('open');
});

// ── Chat WA: unreadCount isolation ───────────────────────────────────────────

it('increments unread_count when updateFromIncoming is called', function () {
    $tenant = makeTenant();
    $conv = makeConversation($tenant->id, ['unread_count' => 0]);

    $conv->updateFromIncoming('Pesan baru dari pelanggan');

    expect($conv->fresh()->unread_count)->toBe(1);
    expect($conv->fresh()->last_message)->toBe('Pesan baru dari pelanggan');
    expect($conv->fresh()->status)->toBe('open');
});

it('reopens a resolved conversation when incoming message arrives', function () {
    $tenant = makeTenant();
    $conv = makeConversation($tenant->id, ['status' => 'resolved', 'unread_count' => 0]);

    $conv->updateFromIncoming('Saya ada masalah lagi');

    expect($conv->fresh()->status)->toBe('open');
    expect($conv->fresh()->unread_count)->toBe(1);
});
