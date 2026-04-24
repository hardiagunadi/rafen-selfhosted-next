<?php

use App\Models\PppProfile;
use App\Models\PppUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makePppFilterTenant(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

function makePppFilterProfile(User $owner, string $name): PppProfile
{
    return PppProfile::query()->create([
        'owner_id' => $owner->id,
        'name' => $name,
        'harga_modal' => 150000,
        'harga_promo' => 150000,
        'ppn' => 11,
        'masa_aktif' => 1,
        'satuan' => 'bulan',
    ]);
}

it('sorts ppp users by due date on datatable endpoint', function () {
    $tenant = makePppFilterTenant();
    $profile = makePppFilterProfile($tenant, 'Paket Sort Tempo');

    PppUser::factory()->forOwner($tenant)->create([
        'ppp_profile_id' => $profile->id,
        'customer_name' => 'Pelanggan Tempo Akhir',
        'username' => 'tempo-akhir',
        'jatuh_tempo' => '2026-04-25',
    ]);

    PppUser::factory()->forOwner($tenant)->create([
        'ppp_profile_id' => $profile->id,
        'customer_name' => 'Pelanggan Tempo Awal',
        'username' => 'tempo-awal',
        'jatuh_tempo' => '2026-04-10',
    ]);

    PppUser::factory()->forOwner($tenant)->create([
        'ppp_profile_id' => $profile->id,
        'customer_name' => 'Pelanggan Tanpa Tempo',
        'username' => 'tanpa-tempo',
        'jatuh_tempo' => null,
    ]);

    $columns = [
        ['data' => 'checkbox'],
        ['data' => 'customer_id'],
        ['data' => 'nama'],
        ['data' => 'tipe'],
        ['data' => 'paket'],
        ['data' => 'diperpanjang'],
        ['data' => 'jatuh_tempo'],
        ['data' => 'renew_print'],
        ['data' => 'aksi'],
        ['data' => 'owner'],
        ['data' => 'teknisi'],
    ];

    $ascendingResponse = $this->actingAs($tenant)->getJson(
        route('ppp-users.datatable').'?'.http_build_query([
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'columns' => $columns,
            'order' => [
                ['column' => 6, 'dir' => 'asc'],
            ],
        ])
    );

    $ascendingResponse->assertSuccessful()
        ->assertJsonPath('recordsTotal', 3)
        ->assertJsonPath('recordsFiltered', 3)
        ->assertJsonCount(3, 'data');

    $ascendingNames = array_column($ascendingResponse->json('data'), 'nama');

    expect($ascendingNames[0])->toContain('Pelanggan Tempo Awal')
        ->and($ascendingNames[1])->toContain('Pelanggan Tempo Akhir')
        ->and($ascendingNames[2])->toContain('Pelanggan Tanpa Tempo');

    $descendingResponse = $this->actingAs($tenant)->getJson(
        route('ppp-users.datatable').'?'.http_build_query([
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'columns' => $columns,
            'order' => [
                ['column' => 6, 'dir' => 'desc'],
            ],
        ])
    );

    $descendingResponse->assertSuccessful()
        ->assertJsonPath('recordsTotal', 3)
        ->assertJsonPath('recordsFiltered', 3)
        ->assertJsonCount(3, 'data');

    $descendingNames = array_column($descendingResponse->json('data'), 'nama');

    expect($descendingNames[0])->toContain('Pelanggan Tempo Akhir')
        ->and($descendingNames[1])->toContain('Pelanggan Tempo Awal')
        ->and($descendingNames[2])->toContain('Pelanggan Tanpa Tempo');
});
