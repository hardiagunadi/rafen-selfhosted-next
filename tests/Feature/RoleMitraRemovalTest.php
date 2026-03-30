<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeRoleRemovalTenantAdmin(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
        'subscription_method' => User::SUBSCRIPTION_METHOD_MONTHLY,
        'trial_days_remaining' => 0,
    ]);
}

it('does not show mitra in the user role list', function () {
    $tenant = makeRoleRemovalTenantAdmin();

    $this->actingAs($tenant)
        ->get(route('users.create'))
        ->assertOk()
        ->assertDontSee('Mitra')
        ->assertSee('Customer Services');
});

it('rejects mitra when creating a user', function () {
    $tenant = makeRoleRemovalTenantAdmin();

    $this->actingAs($tenant)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'name' => 'User Mitra Lama',
            'email' => 'user-mitra@example.test',
            'password' => 'password123',
            'role' => 'mitra',
        ])
        ->assertRedirect(route('users.create'))
        ->assertSessionHasErrors(['role']);

    expect(User::query()->where('email', 'user-mitra@example.test')->exists())->toBeFalse();
});

it('migrates existing mitra users to customer services', function () {
    User::factory()->create([
        'role' => 'mitra',
        'email' => 'mitra-legacy@example.test',
    ]);

    $migration = require database_path('migrations/2026_03_28_092249_convert_mitra_role_to_cs_on_users_table.php');

    $migration->up();

    expect(User::query()->where('role', 'mitra')->count())->toBe(0)
        ->and(User::query()->where('email', 'mitra-legacy@example.test')->value('role'))->toBe('cs');
});
