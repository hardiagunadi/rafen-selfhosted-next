<?php

use App\Models\ShiftDefinition;
use App\Models\ShiftSchedule;
use App\Models\ShiftSwapRequest;
use App\Models\TenantSettings;
use App\Models\User;
use App\Services\WaGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function shiftTenant(): User
{
    $user = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
    TenantSettings::getOrCreate($user->id)->update(['shift_feature_enabled' => true]);

    return $user;
}

function shiftSubUser(int $parentId, string $role): User
{
    return User::factory()->create([
        'parent_id' => $parentId,
        'role' => $role,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

function makeShiftDef(int $ownerId): ShiftDefinition
{
    return ShiftDefinition::create([
        'owner_id' => $ownerId,
        'name' => 'Shift Pagi',
        'start_time' => '08:00',
        'end_time' => '16:00',
        'color' => '#3b82f6',
        'is_active' => true,
    ]);
}

function makeShiftSchedule(int $ownerId, int $userId, int $defId, ?string $date = null): ShiftSchedule
{
    return ShiftSchedule::create([
        'owner_id' => $ownerId,
        'user_id' => $userId,
        'shift_definition_id' => $defId,
        'schedule_date' => $date ?? now()->addDays(1)->toDateString(),
        'status' => 'scheduled',
    ]);
}

// ── Access: shift disabled ─────────────────────────────────────────────────────

it('blocks index when shift feature is disabled', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
    TenantSettings::getOrCreate($tenant->id)->update(['shift_feature_enabled' => false]);

    $this->actingAs($tenant)
        ->get(route('shifts.index'))
        ->assertForbidden();
});

it('blocks teknisi from admin-only shift index', function () {
    $tenant = shiftTenant();
    $tek = shiftSubUser($tenant->id, 'teknisi');

    $this->actingAs($tek)
        ->get(route('shifts.index'))
        ->assertForbidden();
});

it('allows teknisi to view their own schedule', function () {
    $tenant = shiftTenant();
    $tek = shiftSubUser($tenant->id, 'teknisi');

    $this->actingAs($tek)
        ->get(route('shifts.my'))
        ->assertOk();
});

// ── Definitions CRUD ──────────────────────────────────────────────────────────

it('admin can create a shift definition', function () {
    $tenant = shiftTenant();

    $this->actingAs($tenant)
        ->postJson(route('shifts.definitions.store'), [
            'name' => 'Shift Malam',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'color' => '#ef4444',
            'is_active' => true,
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(ShiftDefinition::where('owner_id', $tenant->id)->where('name', 'Shift Malam')->exists())->toBeTrue();
});

it('admin can update a shift definition', function () {
    $tenant = shiftTenant();
    $def = makeShiftDef($tenant->id);

    $this->actingAs($tenant)
        ->putJson(route('shifts.definitions.update', $def), [
            'name' => 'Shift Siang',
            'start_time' => '12:00',
            'end_time' => '20:00',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($def->fresh()->name)->toBe('Shift Siang');
});

it('admin can delete a shift definition', function () {
    $tenant = shiftTenant();
    $def = makeShiftDef($tenant->id);

    $this->actingAs($tenant)
        ->deleteJson(route('shifts.definitions.destroy', $def))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(ShiftDefinition::find($def->id))->toBeNull();
});

it('prevents deleting another tenant shift definition', function () {
    $tenant = shiftTenant();
    $other = shiftTenant();
    $def = makeShiftDef($other->id);

    $this->actingAs($tenant)
        ->deleteJson(route('shifts.definitions.destroy', $def))
        ->assertForbidden();
});

// ── Schedule assign ───────────────────────────────────────────────────────────

it('admin can assign a shift to a user', function () {
    $tenant = shiftTenant();
    $cs = shiftSubUser($tenant->id, 'cs');
    $def = makeShiftDef($tenant->id);
    $date = now()->addDays(3)->toDateString();

    $this->actingAs($tenant)
        ->postJson(route('shifts.schedule.store'), [
            'user_id' => $cs->id,
            'shift_definition_id' => $def->id,
            'schedule_date' => $date,
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(ShiftSchedule::where('user_id', $cs->id)
        ->where('shift_definition_id', $def->id)
        ->whereDate('schedule_date', $date)
        ->exists())->toBeTrue();
});

it('cs role cannot assign shifts', function () {
    $tenant = shiftTenant();
    $cs = shiftSubUser($tenant->id, 'cs');
    $def = makeShiftDef($tenant->id);

    $this->actingAs($cs)
        ->postJson(route('shifts.schedule.store'), [
            'user_id' => $cs->id,
            'shift_definition_id' => $def->id,
            'schedule_date' => now()->addDays(2)->toDateString(),
        ])
        ->assertForbidden();
});

it('admin can delete a schedule', function () {
    $tenant = shiftTenant();
    $cs = shiftSubUser($tenant->id, 'cs');
    $def = makeShiftDef($tenant->id);
    $sched = makeShiftSchedule($tenant->id, $cs->id, $def->id);

    $this->actingAs($tenant)
        ->deleteJson(route('shifts.schedule.destroy', $sched))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(ShiftSchedule::find($sched->id))->toBeNull();
});

// ── Schedule JSON: scope ──────────────────────────────────────────────────────

it('schedule endpoint only returns own tenant data', function () {
    $tenant = shiftTenant();
    $other = shiftTenant();
    $cs = shiftSubUser($tenant->id, 'cs');
    $def1 = makeShiftDef($tenant->id);
    $def2 = makeShiftDef($other->id);
    $cs2 = shiftSubUser($other->id, 'cs');

    $mine = makeShiftSchedule($tenant->id, $cs->id, $def1->id);
    $notMine = makeShiftSchedule($other->id, $cs2->id, $def2->id);

    $from = now()->toDateString();
    $to = now()->addDays(7)->toDateString();

    $res = $this->actingAs($tenant)
        ->getJson(route('shifts.schedule', ['from' => $from, 'to' => $to]))
        ->assertOk()
        ->json('data');

    $ids = collect($res)->pluck('id')->all();
    expect($ids)->toContain($mine->id)->not->toContain($notMine->id);
});

it('teknisi only sees own schedule in schedule endpoint', function () {
    $tenant = shiftTenant();
    $tek = shiftSubUser($tenant->id, 'teknisi');
    $cs = shiftSubUser($tenant->id, 'cs');
    $def = makeShiftDef($tenant->id);

    $mySchedule = makeShiftSchedule($tenant->id, $tek->id, $def->id);
    $csSchedule = makeShiftSchedule($tenant->id, $cs->id, $def->id, now()->addDays(2)->toDateString());

    $from = now()->toDateString();
    $to = now()->addDays(7)->toDateString();

    $res = $this->actingAs($tek)
        ->getJson(route('shifts.schedule', ['from' => $from, 'to' => $to]))
        ->assertOk()
        ->json('data');

    $ids = collect($res)->pluck('id')->all();
    expect($ids)->toContain($mySchedule->id)->not->toContain($csSchedule->id);
});

// ── Swap Request ──────────────────────────────────────────────────────────────

it('employee can request a swap for their own schedule', function () {
    $tenant = shiftTenant();
    $cs = shiftSubUser($tenant->id, 'cs');
    $def = makeShiftDef($tenant->id);
    $sched = makeShiftSchedule($tenant->id, $cs->id, $def->id);

    $this->actingAs($cs)
        ->postJson(route('shifts.swap-requests.store'), [
            'from_schedule_id' => $sched->id,
            'reason' => 'Ada keperluan mendadak',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(ShiftSwapRequest::where('requester_id', $cs->id)->exists())->toBeTrue();
});

it('employee cannot request swap for another person schedule', function () {
    $tenant = shiftTenant();
    $cs1 = shiftSubUser($tenant->id, 'cs');
    $cs2 = shiftSubUser($tenant->id, 'cs');
    $def = makeShiftDef($tenant->id);
    $cs2Schedule = makeShiftSchedule($tenant->id, $cs2->id, $def->id);

    $this->actingAs($cs1)
        ->postJson(route('shifts.swap-requests.store'), [
            'from_schedule_id' => $cs2Schedule->id,
            'reason' => 'Mau ganti',
        ])
        ->assertUnprocessable();
});

it('admin can approve a swap request', function () {
    $tenant = shiftTenant();
    $cs = shiftSubUser($tenant->id, 'cs');
    $def = makeShiftDef($tenant->id);
    $sched = makeShiftSchedule($tenant->id, $cs->id, $def->id);

    $swap = ShiftSwapRequest::create([
        'owner_id' => $tenant->id,
        'requester_id' => $cs->id,
        'from_schedule_id' => $sched->id,
        'status' => 'pending',
    ]);

    $this->actingAs($tenant)
        ->postJson(route('shifts.swap-requests.review', $swap), ['action' => 'approve'])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($swap->fresh()->status)->toBe('approved');
    expect($swap->fresh()->reviewed_by_id)->toBe($tenant->id);
});

it('admin can reject a swap request', function () {
    $tenant = shiftTenant();
    $cs = shiftSubUser($tenant->id, 'cs');
    $def = makeShiftDef($tenant->id);
    $sched = makeShiftSchedule($tenant->id, $cs->id, $def->id);

    $swap = ShiftSwapRequest::create([
        'owner_id' => $tenant->id,
        'requester_id' => $cs->id,
        'from_schedule_id' => $sched->id,
        'status' => 'pending',
    ]);

    $this->actingAs($tenant)
        ->postJson(route('shifts.swap-requests.review', $swap), ['action' => 'reject'])
        ->assertOk();

    expect($swap->fresh()->status)->toBe('rejected');
});

it('cs cannot review a swap request', function () {
    $tenant = shiftTenant();
    $cs = shiftSubUser($tenant->id, 'cs');
    $def = makeShiftDef($tenant->id);
    $sched = makeShiftSchedule($tenant->id, $cs->id, $def->id);

    $swap = ShiftSwapRequest::create([
        'owner_id' => $tenant->id,
        'requester_id' => $cs->id,
        'from_schedule_id' => $sched->id,
        'status' => 'pending',
    ]);

    $this->actingAs($cs)
        ->postJson(route('shifts.swap-requests.review', $swap), ['action' => 'approve'])
        ->assertForbidden();
});

it('sends shift reminders in bulk with deduplicated personal recipients and group summary', function () {
    $payloads = [];
    Http::fake(function ($request) use (&$payloads) {
        if (str_contains($request->url(), '/api/v2/send-message')) {
            $payloads[] = $request->data();

            return Http::response([
                'status' => true,
                'data' => [
                    'messages' => [
                        [
                            'status' => 'queued',
                            'ref_id' => 'shift-reminder-ref',
                        ],
                    ],
                ],
            ], 200);
        }

        return Http::response([], 404);
    });

    $tenant = shiftTenant();
    TenantSettings::getOrCreate($tenant->id)->update([
        'wa_gateway_url' => 'https://gw.example',
        'wa_gateway_token' => 'device-token-abc',
        'wa_shift_group_number' => '120363123456789@g.us',
    ]);

    $employeeA = shiftSubUser($tenant->id, 'cs');
    $employeeB = shiftSubUser($tenant->id, 'teknisi');
    $employeeA->update(['phone' => '081234567890']);
    $employeeB->update(['phone' => '081234567890']);

    $definition = makeShiftDef($tenant->id);
    $targetDate = now()->addDay()->toDateString();
    makeShiftSchedule($tenant->id, $employeeA->id, $definition->id, $targetDate);
    makeShiftSchedule($tenant->id, $employeeB->id, $definition->id, $targetDate);

    $settings = TenantSettings::where('user_id', $tenant->id)->firstOrFail();
    expect($settings->hasWaConfigured())->toBeTrue()
        ->and(WaGatewayService::forTenant($settings))->not->toBeNull()
        ->and(ShiftSchedule::count())->toBe(2);

    $this->actingAs($tenant)
        ->postJson(route('shifts.send-reminders'))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'sent' => 1,
        ]);

    expect($payloads)->toHaveCount(2);

    $messages = collect($payloads)->map(function (array $payload): array {
        return [
            'phone' => (string) data_get($payload, 'data.0.phone', ''),
            'is_group' => (bool) data_get($payload, 'data.0.isGroup', false),
            'message' => (string) data_get($payload, 'data.0.message', ''),
        ];
    });

    $personalMessage = $messages->firstWhere('phone', '6281234567890');
    $groupMessage = $messages->firstWhere('phone', '120363123456789@g.us');

    expect($personalMessage)->not->toBeNull()
        ->and($personalMessage['is_group'])->toBeFalse()
        ->and($personalMessage['message'])->toContain('Pengingat shift besok')
        ->and($groupMessage)->not->toBeNull()
        ->and($groupMessage['is_group'])->toBeTrue()
        ->and($groupMessage['message'])->toContain('Jadwal Shift Besok');
});

// ── Shift Module Toggle ────────────────────────────────────────────────────────

it('tenant admin can enable shift feature via settings', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
    TenantSettings::getOrCreate($tenant->id)->update([
        'module_hotspot_enabled' => true,
        'shift_feature_enabled' => false,
    ]);

    $this->actingAs($tenant)
        ->put(route('tenant-settings.update-modules'), [
            'module_hotspot_enabled' => true,
            'shift_feature_enabled' => true,
        ])
        ->assertRedirect();

    expect(TenantSettings::getOrCreate($tenant->id)->fresh()->shift_feature_enabled)->toBeTrue();
});
