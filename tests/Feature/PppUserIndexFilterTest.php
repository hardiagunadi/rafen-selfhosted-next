<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('propagates isolir filter query to initial datatable state', function () {
    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($tenantAdmin)
        ->get(route('ppp-users.index', ['filter_isolir' => 1]))
        ->assertSuccessful()
        ->assertSee('var initialFilterIsolir = true;', false);
});

it('keeps isolir filter disabled by default when query is absent', function () {
    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($tenantAdmin)
        ->get(route('ppp-users.index'))
        ->assertSuccessful()
        ->assertSee('var initialFilterIsolir = false;', false);
});
