<?php

use App\Models\PppUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns autocomplete suggestions for ppp users by keyword', function () {
    $owner = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    PppUser::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => 'CUST-AC-001',
        'customer_name' => 'Andi Jaringan',
        'username' => 'andi-jrg',
    ]);

    PppUser::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => 'CUST-AC-002',
        'customer_name' => 'Budi Fiber',
        'username' => 'budi-fiber',
    ]);

    $this->actingAs($owner)
        ->getJson(route('ppp-users.autocomplete', ['q' => 'andi']))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.value', 'Andi Jaringan');
});

it('limits autocomplete suggestions to current tenant data', function () {
    $owner = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $otherOwner = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    PppUser::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => 'CUST-TN-001',
        'customer_name' => 'Tenant Sendiri',
        'username' => 'tenant-sendiri',
    ]);

    PppUser::query()->create([
        'owner_id' => $otherOwner->id,
        'customer_id' => 'CUST-TN-999',
        'customer_name' => 'Tenant Lain',
        'username' => 'tenant-lain',
    ]);

    $this->actingAs($owner)
        ->getJson(route('ppp-users.autocomplete', ['q' => 'tenant']))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.value', 'Tenant Sendiri');
});
