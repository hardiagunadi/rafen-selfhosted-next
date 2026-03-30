<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeUserManagerTenant(): User
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

it('shows phone and nickname fields on create user page', function () {
    $tenant = makeUserManagerTenant();

    $this->actingAs($tenant)
        ->get(route('users.create'))
        ->assertOk()
        ->assertSee('Nomor HP / WhatsApp')
        ->assertSee('Nama Panggilan');
});

it('requires phone number when creating teknisi user', function () {
    $tenant = makeUserManagerTenant();

    $this->actingAs($tenant)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'name' => 'Teknisi Tanpa WA',
            'email' => 'teknisi-tanpa-wa@example.test',
            'password' => 'password123',
            'role' => 'teknisi',
            'nickname' => 'Teknisi A',
            'phone' => '',
        ])
        ->assertRedirect(route('users.create'))
        ->assertSessionHasErrors(['phone']);

    expect(User::query()->where('email', 'teknisi-tanpa-wa@example.test')->exists())->toBeFalse();
});

it('stores phone and nickname when creating teknisi user', function () {
    $tenant = makeUserManagerTenant();

    $this->actingAs($tenant)
        ->post(route('users.store'), [
            'name' => 'Teknisi Dengan WA',
            'email' => 'teknisi-dengan-wa@example.test',
            'password' => 'password123',
            'role' => 'teknisi',
            'nickname' => 'Teknisi Lapangan',
            'phone' => '081234567890',
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHasNoErrors();

    $createdUser = User::query()->where('email', 'teknisi-dengan-wa@example.test')->firstOrFail();

    expect($createdUser->parent_id)->toBe($tenant->id)
        ->and($createdUser->role)->toBe('teknisi')
        ->and($createdUser->nickname)->toBe('Teknisi Lapangan')
        ->and($createdUser->phone)->toBe('081234567890');
});

it('updates phone and nickname for existing teknisi user', function () {
    $tenant = makeUserManagerTenant();
    $teknisi = User::factory()->create([
        'parent_id' => $tenant->id,
        'role' => 'teknisi',
        'name' => 'Teknisi Lama',
        'email' => 'teknisi-lama@example.test',
        'phone' => null,
        'nickname' => null,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($tenant)
        ->put(route('users.update', $teknisi), [
            'name' => 'Teknisi Lama',
            'email' => 'teknisi-lama@example.test',
            'role' => 'teknisi',
            'nickname' => 'Teknisi Siaga',
            'phone' => '082345678901',
            'password' => '',
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHasNoErrors();

    expect($teknisi->fresh()->phone)->toBe('082345678901')
        ->and($teknisi->fresh()->nickname)->toBe('Teknisi Siaga');
});
