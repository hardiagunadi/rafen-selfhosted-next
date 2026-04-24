<?php

use App\Models\TenantSettings;
use App\Models\User;
use App\Models\WaConversation;
use App\Models\WaTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function ticketTenant(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ], $attrs));
}

function ticketSubUser(int $parentId, string $role): User
{
    return User::factory()->create([
        'parent_id' => $parentId,
        'role' => $role,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

function makeTicketConversation(int $ownerId): WaConversation
{
    return WaConversation::create([
        'owner_id' => $ownerId,
        'contact_phone' => '628111222333',
        'contact_name' => 'Pelanggan',
        'status' => 'open',
        'unread_count' => 0,
    ]);
}

function makeTicket(int $ownerId, int $convId, array $attrs = []): WaTicket
{
    return WaTicket::create(array_merge([
        'owner_id' => $ownerId,
        'conversation_id' => $convId,
        'title' => 'Internet Mati',
        'type' => 'complaint',
        'priority' => 'normal',
        'status' => 'open',
    ], $attrs));
}

// ── index ──────────────────────────────────────────────────────────────────────

it('allows cs to access ticket index', function () {
    $tenant = ticketTenant();
    $cs = ticketSubUser($tenant->id, 'cs');

    $this->actingAs($cs)->get(route('wa-tickets.index'))->assertOk();
});

it('allows teknisi to access ticket index', function () {
    $tenant = ticketTenant();
    $tek = ticketSubUser($tenant->id, 'teknisi');

    $this->actingAs($tek)->get(route('wa-tickets.index'))->assertOk();
});

it('blocks keuangan from ticket index', function () {
    $tenant = ticketTenant();
    $keu = ticketSubUser($tenant->id, 'keuangan');

    $this->actingAs($keu)->get(route('wa-tickets.index'))->assertForbidden();
});

it('renders the create ticket modal with modal-safe customer select behavior', function () {
    $tenant = ticketTenant();
    $cs = ticketSubUser($tenant->id, 'cs');

    $this->actingAs($cs)
        ->get(route('wa-tickets.index'))
        ->assertOk()
        ->assertSee('id="modalCreateTicket"', false)
        ->assertSee('data-backdrop="static"', false)
        ->assertSee('data-keyboard="false"', false)
        ->assertSee('data-native-select="true"', false)
        ->assertSee('mousedown mouseup click touchstart touchend', false);
});

// ── store ──────────────────────────────────────────────────────────────────────

it('cs can create a ticket from a conversation', function () {
    $tenant = ticketTenant();
    $cs = ticketSubUser($tenant->id, 'cs');
    $conv = makeTicketConversation($tenant->id);

    $this->actingAs($cs)
        ->postJson(route('wa-tickets.store'), [
            'conversation_id' => $conv->id,
            'title' => 'Gangguan Internet',
            'type' => 'troubleshoot',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(WaTicket::where('conversation_id', $conv->id)->exists())->toBeTrue();
});

it('prevents cs from creating ticket on another tenant conversation', function () {
    $tenant = ticketTenant();
    $other = ticketTenant();
    $cs = ticketSubUser($tenant->id, 'cs');
    $conv = makeTicketConversation($other->id);

    $this->actingAs($cs)
        ->postJson(route('wa-tickets.store'), [
            'conversation_id' => $conv->id,
            'title' => 'Injeksi',
            'type' => 'complaint',
        ])
        ->assertForbidden();
});

// ── datatable isolation ────────────────────────────────────────────────────────

it('datatable only returns own tenant tickets', function () {
    $tenant = ticketTenant();
    $other = ticketTenant();

    $conv1 = makeTicketConversation($tenant->id);
    $conv2 = makeTicketConversation($other->id);
    $mine = makeTicket($tenant->id, $conv1->id);
    $notMine = makeTicket($other->id, $conv2->id);

    $res = $this->actingAs($tenant)
        ->getJson(route('wa-tickets.datatable'))
        ->assertOk()
        ->json('data');

    $ids = collect($res)->pluck('id')->all();
    expect($ids)->toContain($mine->id)->not->toContain($notMine->id);
});

it('teknisi only sees own assigned tickets in datatable', function () {
    $tenant = ticketTenant();
    $tek = ticketSubUser($tenant->id, 'teknisi');
    $conv = makeTicketConversation($tenant->id);

    $assignedToMe = makeTicket($tenant->id, $conv->id, ['assigned_to_id' => $tek->id]);
    $notAssigned = makeTicket($tenant->id, $conv->id, ['title' => 'Lain']);

    $res = $this->actingAs($tek)
        ->getJson(route('wa-tickets.datatable'))
        ->assertOk()
        ->json('data');

    $ids = collect($res)->pluck('id')->all();
    expect($ids)->toContain($assignedToMe->id)->not->toContain($notAssigned->id);
});

it('sends whatsapp notification to assigned teknisi with follow-up and progress reminder', function () {
    $capturedMessages = [];

    Http::fake(function ($request) use (&$capturedMessages) {
        if (! str_contains($request->url(), '/api/v2/send-message')) {
            return Http::response([], 404);
        }

        $payload = $request->data();

        foreach ($payload['data'] ?? [] as $message) {
            $capturedMessages[] = $message;
        }

        return Http::response([
            'status' => true,
            'data' => [
                'messages' => [
                    [
                        'status' => 'queued',
                        'ref_id' => 'ticket-assigned-ref',
                    ],
                ],
            ],
        ], 200);
    });

    $tenant = ticketTenant();
    $cs = ticketSubUser($tenant->id, 'cs');
    $teknisi = ticketSubUser($tenant->id, 'teknisi');
    $teknisi->update([
        'name' => 'Teknisi Rafen',
        'phone' => '081234567890',
    ]);

    TenantSettings::getOrCreate($tenant->id)->update([
        'wa_gateway_url' => 'https://gateway.example/wa',
        'wa_gateway_token' => 'device-token-ticket',
        'wa_msg_randomize' => false,
    ]);

    $conversation = makeTicketConversation($tenant->id);
    $conversation->update(['contact_name' => 'Pelanggan Fiber A']);

    $ticket = makeTicket($tenant->id, $conversation->id, [
        'title' => 'Internet putus sejak pagi',
        'priority' => 'high',
    ]);

    $this->actingAs($cs)
        ->postJson(route('wa-tickets.assign', $ticket), [
            'assigned_to_id' => $teknisi->id,
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($capturedMessages)->toHaveCount(1);

    $assignmentNote = $ticket->fresh()->notes()->latest('id')->first();
    expect($assignmentNote)->not->toBeNull()
        ->and($assignmentNote->type)->toBe('assigned')
        ->and($assignmentNote->meta)->toBe('Assign teknisi: Teknisi Rafen');

    $this->actingAs($cs)
        ->get(route('wa-tickets.show', $ticket))
        ->assertOk()
        ->assertSeeText('Assign Baru')
        ->assertSeeText('Assign teknisi: Teknisi Rafen');

    $message = $capturedMessages[0];

    expect($message['phone'] ?? null)->toBe('6281234567890')
        ->and($message['message'] ?? null)->toContain('Halo Teknisi Rafen')
        ->and($message['message'] ?? null)->toContain('No. Tiket: #'.$ticket->id)
        ->and($message['message'] ?? null)->toContain('Judul: Internet putus sejak pagi')
        ->and($message['message'] ?? null)->toContain('Pelanggan: Pelanggan Fiber A')
        ->and($message['message'] ?? null)->toContain('Prioritas: Tinggi')
        ->and($message['message'] ?? null)->toContain('Mohon segera tindak lanjuti tiket ini')
        ->and($message['message'] ?? null)->toContain('update status atau progres pekerjaan di RAFEN')
        ->and($message['message'] ?? null)->toContain(route('wa-tickets.show', $ticket));
});

it('sends notifications to new and previous teknisi when ticket is reassigned', function () {
    $capturedMessages = [];

    Http::fake(function ($request) use (&$capturedMessages) {
        if (! str_contains($request->url(), '/api/v2/send-message')) {
            return Http::response([], 404);
        }

        $payload = $request->data();

        foreach ($payload['data'] ?? [] as $message) {
            $capturedMessages[] = $message;
        }

        return Http::response([
            'status' => true,
            'data' => [
                'messages' => [
                    [
                        'status' => 'queued',
                        'ref_id' => 'ticket-reassigned-ref',
                    ],
                ],
            ],
        ], 200);
    });

    $tenant = ticketTenant();
    $cs = ticketSubUser($tenant->id, 'cs');
    $teknisiAwal = ticketSubUser($tenant->id, 'teknisi');
    $teknisiBaru = ticketSubUser($tenant->id, 'teknisi');

    $teknisiAwal->update(['name' => 'Teknisi Lama', 'phone' => '081111111111']);
    $teknisiBaru->update(['name' => 'Teknisi Baru', 'phone' => '082222222222']);

    TenantSettings::getOrCreate($tenant->id)->update([
        'wa_gateway_url' => 'https://gateway.example/wa',
        'wa_gateway_token' => 'device-token-ticket',
        'wa_msg_randomize' => false,
    ]);

    $conversation = makeTicketConversation($tenant->id);
    $ticket = makeTicket($tenant->id, $conversation->id, [
        'title' => 'Perangkat pelanggan offline',
        'priority' => 'normal',
        'assigned_to_id' => $teknisiAwal->id,
    ]);

    $this->actingAs($cs)
        ->postJson(route('wa-tickets.assign', $ticket), [
            'assigned_to_id' => $teknisiBaru->id,
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($capturedMessages)->toHaveCount(2);

    $assignmentNote = $ticket->fresh()->notes()->latest('id')->first();
    expect($assignmentNote)->not->toBeNull()
        ->and($assignmentNote->type)->toBe('reassigned')
        ->and($assignmentNote->meta)->toBe('Assign ulang teknisi: Teknisi Lama -> Teknisi Baru');

    $this->actingAs($cs)
        ->get(route('wa-tickets.show', $ticket))
        ->assertOk()
        ->assertSeeText('Assign Ulang')
        ->assertSeeText('Assign ulang teknisi: Teknisi Lama -> Teknisi Baru');

    $messageToNewTeknisi = collect($capturedMessages)
        ->firstWhere('phone', '6282222222222');

    $messageToPreviousTeknisi = collect($capturedMessages)
        ->firstWhere('phone', '6281111111111');

    expect($messageToNewTeknisi)->not->toBeNull()
        ->and($messageToNewTeknisi['message'] ?? null)->toContain('Halo Teknisi Baru')
        ->and($messageToNewTeknisi['message'] ?? null)->toContain('Penugasan tiket berikut telah diperbarui untuk Anda')
        ->and($messageToNewTeknisi['message'] ?? null)->toContain('No. Tiket: #'.$ticket->id)
        ->and($messageToNewTeknisi['message'] ?? null)->toContain('update status atau progres pekerjaan di RAFEN');

    expect($messageToPreviousTeknisi)->not->toBeNull()
        ->and($messageToPreviousTeknisi['message'] ?? null)->toContain('Halo Teknisi Lama')
        ->and($messageToPreviousTeknisi['message'] ?? null)->toContain('Penugasan tiket berikut sudah dialihkan ke Teknisi Baru')
        ->and($messageToPreviousTeknisi['message'] ?? null)->toContain('Anda tidak perlu menindaklanjuti tiket ini lagi');

    expect($ticket->fresh()->assigned_to_id)->toBe($teknisiBaru->id);
});

// ── update ─────────────────────────────────────────────────────────────────────

it('cs can update ticket status', function () {
    $tenant = ticketTenant();
    $cs = ticketSubUser($tenant->id, 'cs');
    $conv = makeTicketConversation($tenant->id);
    $ticket = makeTicket($tenant->id, $conv->id);

    $this->actingAs($cs)
        ->putJson(route('wa-tickets.update', $ticket), ['status' => 'in_progress'])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($ticket->fresh()->status)->toBe('in_progress');
});

it('sets resolved_at when ticket is resolved', function () {
    $tenant = ticketTenant();
    $conv = makeTicketConversation($tenant->id);
    $ticket = makeTicket($tenant->id, $conv->id);

    $this->actingAs($tenant)
        ->putJson(route('wa-tickets.update', $ticket), ['status' => 'resolved'])
        ->assertOk();

    expect($ticket->fresh()->resolved_at)->not->toBeNull();
});

it('teknisi cannot update ticket not assigned to them', function () {
    $tenant = ticketTenant();
    $tek = ticketSubUser($tenant->id, 'teknisi');
    $conv = makeTicketConversation($tenant->id);
    $ticket = makeTicket($tenant->id, $conv->id); // assigned_to_id = null

    $this->actingAs($tek)
        ->putJson(route('wa-tickets.update', $ticket), ['status' => 'in_progress'])
        ->assertForbidden();
});

// ── destroy ────────────────────────────────────────────────────────────────────

it('admin can delete a ticket', function () {
    $tenant = ticketTenant();
    $conv = makeTicketConversation($tenant->id);
    $ticket = makeTicket($tenant->id, $conv->id);

    $this->actingAs($tenant)
        ->deleteJson(route('wa-tickets.destroy', $ticket))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(WaTicket::find($ticket->id))->toBeNull();
});

it('prevents deleting another tenant ticket', function () {
    $tenant = ticketTenant();
    $other = ticketTenant();
    $conv = makeTicketConversation($other->id);
    $ticket = makeTicket($other->id, $conv->id);

    $this->actingAs($tenant)
        ->deleteJson(route('wa-tickets.destroy', $ticket))
        ->assertForbidden();
});
