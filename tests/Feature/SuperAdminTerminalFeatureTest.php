<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSuperAdmin(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => true,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

function makeTerminalTenantAdmin(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

it('allows super admin to open terminal page', function () {
    $superAdmin = makeSuperAdmin();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.terminal.index'))
        ->assertSuccessful()
        ->assertSee('Terminal Super Admin')
        ->assertSee('Quick Command')
        ->assertSee('Self-Hosted Manifest')
        ->assertSee('Self-Hosted Materialize Repo');
});

it('blocks tenant admin from opening terminal page', function () {
    $tenantAdmin = makeTerminalTenantAdmin();

    $this->actingAs($tenantAdmin)
        ->get(route('super-admin.terminal.index'))
        ->assertForbidden();
});

it('runs allowed help center command for super admin', function () {
    $superAdmin = makeSuperAdmin();

    $this->actingAs($superAdmin)
        ->postJson(route('super-admin.terminal.run'), [
            'command' => 'php artisan list --raw',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('exit_code', 0)
        ->assertJsonStructure([
            'success',
            'message',
            'command',
            'exit_code',
            'duration_ms',
            'output',
        ]);
});

it('runs allowed self hosted command for super admin', function () {
    $superAdmin = makeSuperAdmin();

    $this->actingAs($superAdmin)
        ->postJson(route('super-admin.terminal.run'), [
            'command' => 'php artisan self-hosted:manifest --json',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('exit_code', 0)
        ->assertJsonPath('command', 'php artisan self-hosted:manifest --json');
});

it('rejects command outside help center scope', function () {
    $superAdmin = makeSuperAdmin();

    $this->actingAs($superAdmin)
        ->postJson(route('super-admin.terminal.run'), [
            'command' => 'rm -rf /tmp/rafen-test',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false);
});
