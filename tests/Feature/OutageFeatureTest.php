<?php

use App\Jobs\SendOutageWaBlastJob;
use App\Models\Odp;
use App\Models\Outage;
use App\Models\OutageAffectedArea;
use App\Models\OutageUpdate;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeOutageTenant(): User
{
    return User::factory()->create([
        'role'                    => 'administrator',
        'is_super_admin'          => false,
        'subscription_status'     => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

function makeOutageSubUser(int $parentId, string $role = 'noc'): User
{
    return User::factory()->create([
        'parent_id'     => $parentId,
        'role'          => $role,
        'is_super_admin'=> false,
    ]);
}

function basePayload(array $override = []): array
{
    return array_merge([
        'title'      => 'Putus Fiber Backbone Jalur A',
        'severity'   => 'high',
        'started_at' => now()->format('Y-m-d\TH:i'),
    ], $override);
}

// ─── store: data tersimpan ───────────────────────────────────────────────────

it('tenant admin dapat menyimpan outage baru', function () {
    Queue::fake();

    $tenant = makeOutageTenant();

    $response = $this->actingAs($tenant)
        ->postJson(route('outages.store'), basePayload());

    $response->assertOk()->assertJsonFragment(['success' => true]);

    expect(Outage::query()->where('title', 'Putus Fiber Backbone Jalur A')->exists())->toBeTrue();

    $outage = Outage::query()->first();
    expect($outage->owner_id)->toBe($tenant->id);
    expect($outage->status)->toBe(Outage::STATUS_OPEN);
    expect($outage->severity)->toBe('high');
    expect($outage->created_by_id)->toBe($tenant->id);
    expect($outage->public_token)->not->toBeEmpty();
});

it('menyimpan outage update awal saat dibuat', function () {
    Queue::fake();

    $tenant = makeOutageTenant();

    $this->actingAs($tenant)->postJson(route('outages.store'), basePayload());

    $outage = Outage::query()->first();

    expect(
        OutageUpdate::where('outage_id', $outage->id)
            ->where('type', 'created')
            ->exists()
    )->toBeTrue();
});

it('menyimpan keyword area dan ODP terpilih', function () {
    Queue::fake();

    $tenant = makeOutageTenant();
    $odp = Odp::factory()->create(['owner_id' => $tenant->id]);

    $this->actingAs($tenant)->postJson(route('outages.store'), basePayload([
        'odp_ids'      => [$odp->id],
        'custom_areas' => ['Desa Semayu', 'Kel. Wonoroto'],
    ]));

    $outage = Outage::query()->first();

    expect(
        OutageAffectedArea::where('outage_id', $outage->id)
            ->where('area_type', 'odp')
            ->where('odp_id', $odp->id)
            ->exists()
    )->toBeTrue();

    expect(
        OutageAffectedArea::where('outage_id', $outage->id)
            ->where('area_type', 'keyword')
            ->where('label', 'Desa Semayu')
            ->exists()
    )->toBeTrue();

    expect(
        OutageAffectedArea::where('outage_id', $outage->id)
            ->where('area_type', 'keyword')
            ->where('label', 'Kel. Wonoroto')
            ->exists()
    )->toBeTrue();
});

it('meng-assign teknisi ke outage saat dibuat', function () {
    Queue::fake();

    $tenant  = makeOutageTenant();
    $teknisi = makeOutageSubUser($tenant->id, 'teknisi');

    $this->actingAs($tenant)->postJson(route('outages.store'), basePayload([
        'assigned_teknisi_id' => $teknisi->id,
    ]));

    $outage = Outage::query()->first();

    expect($outage->assigned_teknisi_id)->toBe($teknisi->id);

    expect(
        OutageUpdate::where('outage_id', $outage->id)
            ->where('type', 'assigned')
            ->exists()
    )->toBeTrue();
});

it('mengirim WA blast job saat send_wa_blast=1', function () {
    Queue::fake();

    $tenant = makeOutageTenant();

    $this->actingAs($tenant)->postJson(route('outages.store'), basePayload([
        'send_wa_blast' => true,
    ]));

    Queue::assertPushed(SendOutageWaBlastJob::class, function ($job) {
        return $job->blastType === 'initial';
    });
});

it('tidak mengirim WA blast job saat send_wa_blast tidak diisi', function () {
    Queue::fake();

    $tenant = makeOutageTenant();

    $this->actingAs($tenant)->postJson(route('outages.store'), basePayload());

    Queue::assertNotPushed(SendOutageWaBlastJob::class);
});

// ─── store via AJAX (simulasi FormData browser dengan X-Requested-With) ─────

it('store mengembalikan JSON saat request ajax (X-Requested-With)', function () {
    Queue::fake();

    $tenant = makeOutageTenant();

    $response = $this->actingAs($tenant)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->post(route('outages.store'), basePayload());

    $response->assertOk()->assertJsonFragment(['success' => true]);

    expect(Outage::query()->count())->toBe(1);
});

// ─── validasi input ──────────────────────────────────────────────────────────

it('store gagal tanpa title', function () {
    $tenant = makeOutageTenant();

    $this->actingAs($tenant)
        ->postJson(route('outages.store'), [
            'severity'   => 'medium',
            'started_at' => now()->format('Y-m-d\TH:i'),
        ])
        ->assertUnprocessable();

    expect(Outage::query()->count())->toBe(0);
});

it('store gagal dengan severity tidak valid', function () {
    $tenant = makeOutageTenant();

    $this->actingAs($tenant)
        ->postJson(route('outages.store'), basePayload(['severity' => 'ultra']))
        ->assertUnprocessable();
});

// ─── hak akses ──────────────────────────────────────────────────────────────

it('sub-user noc dapat membuat outage', function () {
    Queue::fake();

    $tenant  = makeOutageTenant();
    $noc     = makeOutageSubUser($tenant->id, 'noc');

    $this->actingAs($noc)
        ->postJson(route('outages.store'), basePayload())
        ->assertOk()->assertJsonFragment(['success' => true]);

    expect(Outage::query()->first()->owner_id)->toBe($tenant->id);
});

it('sub-user keuangan tidak dapat membuat outage', function () {
    $tenant   = makeOutageTenant();
    $keuangan = makeOutageSubUser($tenant->id, 'keuangan');

    $this->actingAs($keuangan)
        ->postJson(route('outages.store'), basePayload())
        ->assertForbidden();
});

it('sub-user cs tidak dapat membuat outage', function () {
    $tenant = makeOutageTenant();
    $cs     = makeOutageSubUser($tenant->id, 'cs');

    $this->actingAs($cs)
        ->postJson(route('outages.store'), basePayload())
        ->assertForbidden();
});

it('tenant lain tidak dapat melihat outage tenant berbeda', function () {
    Queue::fake();

    $tenantA = makeOutageTenant();
    $tenantB = makeOutageTenant();

    $this->actingAs($tenantA)->postJson(route('outages.store'), basePayload());
    $outage = Outage::query()->first();

    $this->actingAs($tenantB)
        ->get(route('outages.show', $outage))
        ->assertForbidden();
});

// ─── testBlast ───────────────────────────────────────────────────────────────

it('testBlast mengembalikan error jika wa gateway tidak dikonfigurasi', function () {
    $tenant = makeOutageTenant();

    // Tidak ada TenantSettings — gateway tidak ada
    $this->actingAs($tenant)
        ->postJson(route('outages.test-blast'), [
            'test_phone' => '6281234567890',
        ])
        ->assertUnprocessable()
        ->assertJsonFragment(['success' => false]);
});

it('testBlast mengembalikan recipient_count dan mengirim pesan', function () {
    $tenant = makeOutageTenant();

    TenantSettings::create([
        'user_id'          => $tenant->id,
        'wa_gateway_url'   => 'http://wa-test.local',
        'wa_gateway_token' => 'test-token',
        'wa_session_id'    => 'default',
        'business_phone'   => '6281234567890',
    ]);

    PppUser::factory()->count(3)->create([
        'owner_id'    => $tenant->id,
        'status_akun' => 'enable',
        'nomor_hp'    => '6281000000001',
        'alamat'      => 'Desa Semayu RT 01',
    ]);
    PppUser::factory()->create([
        'owner_id'    => $tenant->id,
        'status_akun' => 'enable',
        'nomor_hp'    => '6281000000005',
        'alamat'      => 'Jalan Raya Kota',
    ]);

    // Fake HTTP agar WaGatewayService::sendMessage tidak benar-benar keluar
    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response([
            'status'  => true,
            'message' => 'Processed',
            'data'    => ['messages' => [['ref_id' => 'test-ref', 'status' => 'queued']]],
        ], 200),
    ]);

    $response = $this->actingAs($tenant)
        ->postJson(route('outages.test-blast'), [
            'test_phone'   => '6281234567890',
            'custom_areas' => ['Semayu'],
        ]);

    $response->assertOk()
        ->assertJsonFragment(['success' => true])
        ->assertJsonStructure(['success', 'message', 'recipient_count']);

    expect($response->json('recipient_count'))->toBe(3);
});

it('testBlast menghitung semua pelanggan jika area kosong', function () {
    $tenant = makeOutageTenant();

    TenantSettings::create([
        'user_id'          => $tenant->id,
        'wa_gateway_url'   => 'http://wa-test.local',
        'wa_gateway_token' => 'test-token',
        'wa_session_id'    => 'default',
        'business_phone'   => '6281234567890',
    ]);

    PppUser::factory()->count(5)->create([
        'owner_id'    => $tenant->id,
        'status_akun' => 'enable',
        'nomor_hp'    => '6281000000099',
    ]);

    \Illuminate\Support\Facades\Http::fake(function () {
        return \Illuminate\Support\Facades\Http::response([
            'status'  => true,
            'message' => 'Processed',
            'data'    => ['messages' => [['ref_id' => 'ref-x', 'status' => 'queued']]],
        ], 200);
    });

    $response = $this->actingAs($tenant)
        ->postJson(route('outages.test-blast'), [
            'test_phone' => '6281234567890',
        ]);

    $response->assertOk()->assertJsonFragment(['success' => true]);
    // recipient_count = 5 karena area tidak dibatasi
    expect($response->json('recipient_count'))->toBe(5);
});

// ─── SendOutageWaBlastJob: blast tersimpan di OutageUpdate ──────────────────

it('SendOutageWaBlastJob mencatat log pengiriman di outage_updates', function () {
    $tenant = makeOutageTenant();

    TenantSettings::create([
        'user_id'          => $tenant->id,
        'wa_gateway_url'   => 'http://wa-test.local',
        'wa_gateway_token' => 'test-token',
        'wa_session_id'    => 'default',
    ]);

    PppUser::factory()->count(2)->create([
        'owner_id'    => $tenant->id,
        'status_akun' => 'enable',
        'nomor_hp'    => '6281111111111',
        'alamat'      => 'Kampung Maju',
    ]);

    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response([
            'status'  => true,
            'message' => 'Processed',
            'data'    => ['messages' => [['ref_id' => 'r1', 'status' => 'queued']]],
        ], 200),
    ]);

    // Buat outage, lalu dispatch job secara eksplisit (sync) untuk test
    $this->actingAs($tenant)->postJson(route('outages.store'), array_merge(basePayload(), [
        'custom_areas'  => ['Kampung Maju'],
        'send_wa_blast' => false, // jangan dispatch via store — kita dispatch manual di bawah
    ]));

    $outage = Outage::query()->first();

    // Jalankan job secara synchronous
    dispatch_sync(new SendOutageWaBlastJob($outage->id, $tenant->id, 'initial', $tenant->id));

    // wa_blast_count harus > 0 karena ada 2 penerima
    expect($outage->fresh()->wa_blast_count)->toBeGreaterThan(0);

    // OutageUpdate dengan body berisi "Notifikasi WA" harus ada
    $blastNote = OutageUpdate::where('outage_id', $outage->id)
        ->where('type', 'note')
        ->where('body', 'like', '%Notifikasi WA%')
        ->first();

    expect($blastNote)->not->toBeNull();
});

// ─── include_status_link ─────────────────────────────────────────────────────

it('menyimpan include_status_link=true secara default', function () {
    Queue::fake();

    $tenant = makeOutageTenant();

    $this->actingAs($tenant)->postJson(route('outages.store'), basePayload());

    expect(Outage::query()->first()->include_status_link)->toBeTrue();
});

it('menyimpan include_status_link=false jika dinonaktifkan', function () {
    Queue::fake();

    $tenant = makeOutageTenant();

    $this->actingAs($tenant)->postJson(route('outages.store'), basePayload([
        'include_status_link' => false,
    ]));

    expect(Outage::query()->first()->include_status_link)->toBeFalse();
});

it('pesan WA tidak mengandung link status jika include_status_link=false', function () {
    $tenant = makeOutageTenant();

    TenantSettings::create([
        'user_id'          => $tenant->id,
        'wa_gateway_url'   => 'http://wa-test.local',
        'wa_gateway_token' => 'test-token',
        'wa_session_id'    => 'default',
    ]);

    PppUser::factory()->count(1)->create([
        'owner_id'    => $tenant->id,
        'status_akun' => 'enable',
        'nomor_hp'    => '6281222222222',
        'alamat'      => 'Gang Kenanga',
    ]);

    $capturedMessages = [];
    \Illuminate\Support\Facades\Http::fake(function ($request) use (&$capturedMessages) {
        $body = json_decode($request->body(), true);
        foreach ($body['data'] ?? [] as $msg) {
            $capturedMessages[] = $msg['message'] ?? '';
        }
        return \Illuminate\Support\Facades\Http::response([
            'status'  => true,
            'message' => 'Processed',
            'data'    => ['messages' => [['ref_id' => 'r-nolink', 'status' => 'queued']]],
        ], 200);
    });

    $this->actingAs($tenant)->postJson(route('outages.store'), array_merge(basePayload(), [
        'custom_areas'        => ['Gang Kenanga'],
        'send_wa_blast'       => false,
        'include_status_link' => false,
    ]));

    $outage = Outage::query()->first();
    dispatch_sync(new SendOutageWaBlastJob($outage->id, $tenant->id, 'initial', $tenant->id));

    expect($capturedMessages)->not->toBeEmpty();
    foreach ($capturedMessages as $msg) {
        expect($msg)->not->toContain('/status/');
    }
});

it('pesan WA mengandung link status jika include_status_link=true', function () {
    $tenant = makeOutageTenant();

    TenantSettings::create([
        'user_id'          => $tenant->id,
        'wa_gateway_url'   => 'http://wa-test.local',
        'wa_gateway_token' => 'test-token',
        'wa_session_id'    => 'default',
    ]);

    PppUser::factory()->count(1)->create([
        'owner_id'    => $tenant->id,
        'status_akun' => 'enable',
        'nomor_hp'    => '6281333333333',
        'alamat'      => 'Gang Melati',
    ]);

    $capturedMessages = [];
    \Illuminate\Support\Facades\Http::fake(function ($request) use (&$capturedMessages) {
        $body = json_decode($request->body(), true);
        foreach ($body['data'] ?? [] as $msg) {
            $capturedMessages[] = $msg['message'] ?? '';
        }
        return \Illuminate\Support\Facades\Http::response([
            'status'  => true,
            'message' => 'Processed',
            'data'    => ['messages' => [['ref_id' => 'r-withlink', 'status' => 'queued']]],
        ], 200);
    });

    $this->actingAs($tenant)->postJson(route('outages.store'), array_merge(basePayload(), [
        'custom_areas'        => ['Gang Melati'],
        'send_wa_blast'       => false,
        'include_status_link' => true,
    ]));

    $outage = Outage::query()->first();
    dispatch_sync(new SendOutageWaBlastJob($outage->id, $tenant->id, 'initial', $tenant->id));

    expect($capturedMessages)->not->toBeEmpty();
    expect($capturedMessages[0])->toContain('/status/');
});

it('testBlast tidak menyertakan link jika include_status_link=false', function () {
    $tenant = makeOutageTenant();

    TenantSettings::create([
        'user_id'          => $tenant->id,
        'wa_gateway_url'   => 'http://wa-test.local',
        'wa_gateway_token' => 'test-token',
        'wa_session_id'    => 'default',
    ]);

    $capturedMessages = [];
    \Illuminate\Support\Facades\Http::fake(function ($request) use (&$capturedMessages) {
        $body = json_decode($request->body(), true);
        foreach ($body['data'] ?? [] as $msg) {
            $capturedMessages[] = $msg['message'] ?? '';
        }
        return \Illuminate\Support\Facades\Http::response([
            'status'  => true,
            'message' => 'Processed',
            'data'    => ['messages' => [['ref_id' => 'r-tbnolink', 'status' => 'queued']]],
        ], 200);
    });

    $this->actingAs($tenant)
        ->postJson(route('outages.test-blast'), [
            'test_phone'          => '6281234567890',
            'include_status_link' => false,
        ]);

    expect($capturedMessages)->not->toBeEmpty();
    foreach ($capturedMessages as $msg) {
        expect($msg)->not->toContain('/status/');
    }
});
