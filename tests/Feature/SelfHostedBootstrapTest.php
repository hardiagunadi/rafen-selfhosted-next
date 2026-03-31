<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    putenv('LICENSE_SELF_HOSTED_ENABLED=true');
    putenv('LICENSE_ENFORCE=true');
    putenv('APP_KEY=base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=');
    $_ENV['LICENSE_SELF_HOSTED_ENABLED'] = 'true';
    $_ENV['LICENSE_ENFORCE'] = 'true';
    $_ENV['APP_KEY'] = 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=';
    $_SERVER['LICENSE_SELF_HOSTED_ENABLED'] = 'true';
    $_SERVER['LICENSE_ENFORCE'] = 'true';
    $_SERVER['APP_KEY'] = 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=';

    $this->refreshApplication();
    $this->artisan('migrate');

    config()->set('license.self_hosted_enabled', true);
    config()->set('license.enforce', true);
    config()->set('app.key', 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=');
});

it('shows the login page', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('Masuk ke Rafen Self-Hosted');
});

it('allows a super admin to sign in and open the license page', function () {
    $user = User::factory()->superAdmin()->create([
        'password' => Hash::make('secret-123'),
    ]);

    $this->post(route('login'), [
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
