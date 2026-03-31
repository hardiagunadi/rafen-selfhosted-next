<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores phone number when creating the initial super admin', function () {
    $this->artisan('user:create-super-admin', [
        'name' => 'Super Admin Awal',
        'email' => 'superadmin@example.test',
        '--password' => 'secret-123',
        '--phone' => '081234567890',
    ])
        ->expectsOutputToContain('Super admin berhasil disiapkan.')
        ->expectsOutputToContain('Phone    : 081234567890')
        ->assertSuccessful();

    $user = User::query()->where('email', 'superadmin@example.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->is_super_admin)->toBeTrue()
        ->and($user->phone)->toBe('081234567890');
});
