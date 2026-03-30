<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('shows the login page', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('Masuk ke Rafen Self-Hosted');
});

it('allows a super admin to sign in and open the license page', function () {
    $user = User::factory()->superAdmin()->create([
        'password' => Hash::make('secret-123'),
    ]);

    $this->post(route('login.attempt'), [
        'email' => $user->email,
        'password' => 'secret-123',
    ])->assertRedirect(route('super-admin.settings.license'));

    $this->actingAs($user)
        ->get(route('super-admin.settings.license'))
        ->assertSuccessful();
});

it('blocks a non super admin from the license page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('super-admin.settings.license'))
        ->assertForbidden();
});