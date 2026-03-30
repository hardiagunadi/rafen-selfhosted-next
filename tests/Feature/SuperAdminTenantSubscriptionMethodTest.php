<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createPlan(array $overrides = []): SubscriptionPlan
{
    return SubscriptionPlan::query()->create(array_merge([
        'name' => 'Starter',
        'slug' => 'starter-'.fake()->unique()->slug(),
        'description' => 'Plan test',
        'price' => 100000,
        'duration_days' => 30,
        'max_mikrotik' => 3,
        'max_ppp_users' => 100,
        'features' => ['Basic'],
        'is_active' => true,
        'is_featured' => false,
        'sort_order' => 1,
    ], $overrides));
}

function createSuperAdmin(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => true,
    ]);
}

it('allows super admin to set tenant subscription method to license with custom limits', function () {
    $superAdmin = createSuperAdmin();
    $plan = createPlan();
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'trial',
        'trial_days_remaining' => 14,
        'subscription_method' => User::SUBSCRIPTION_METHOD_MONTHLY,
    ]);

    $this->actingAs($superAdmin)
        ->put(route('super-admin.tenants.update', $tenant), [
            'name' => $tenant->name,
            'email' => $tenant->email,
            'subscription_status' => 'active',
            'subscription_plan_id' => $plan->id,
            'subscription_method' => User::SUBSCRIPTION_METHOD_LICENSE,
            'license_max_mikrotik' => 8,
            'license_max_ppp_users' => 250,
            'trial_days_remaining' => 0,
            'vpn_enabled' => false,
        ])
        ->assertRedirect(route('super-admin.tenants.show', $tenant));

    $tenant->refresh();

    expect($tenant->subscription_method)->toBe(User::SUBSCRIPTION_METHOD_LICENSE)
        ->and($tenant->license_max_mikrotik)->toBe(8)
        ->and($tenant->license_max_ppp_users)->toBe(250)
        ->and($tenant->subscription_status)->toBe('active')
        ->and($tenant->trial_days_remaining)->toBe(0);
});

it('creates license tenant as active without trial period', function () {
    $superAdmin = createSuperAdmin();
    $plan = createPlan();
    $email = 'license-tenant-'.fake()->unique()->safeEmail();
    $adminSubdomain = 'license-'.Str::lower(Str::random(12));

    $this->actingAs($superAdmin)
        ->post(route('super-admin.tenants.store'), [
            'name' => 'Tenant Lisensi',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'admin_subdomain' => $adminSubdomain,
            'subscription_plan_id' => $plan->id,
            'subscription_method' => User::SUBSCRIPTION_METHOD_LICENSE,
            'license_max_mikrotik' => 10,
            'license_max_ppp_users' => 500,
            'trial_days' => 30,
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $tenant = User::query()->where('email', $email)->firstOrFail();

    expect($tenant->subscription_method)->toBe(User::SUBSCRIPTION_METHOD_LICENSE)
        ->and($tenant->subscription_status)->toBe('active')
        ->and($tenant->trial_days_remaining)->toBe(0)
        ->and($tenant->subscription_expires_at?->toDateString())
        ->toBe(now()->addDays(User::LICENSE_DURATION_DAYS)->toDateString());
});

it('forces active status when tenant is switched to license even if trial is submitted', function () {
    $superAdmin = createSuperAdmin();
    $plan = createPlan();
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'trial',
        'trial_days_remaining' => 14,
        'subscription_method' => User::SUBSCRIPTION_METHOD_MONTHLY,
        'subscription_expires_at' => null,
    ]);

    $this->actingAs($superAdmin)
        ->put(route('super-admin.tenants.update', $tenant), [
            'name' => $tenant->name,
            'email' => $tenant->email,
            'subscription_status' => 'trial',
            'subscription_plan_id' => $plan->id,
            'subscription_method' => User::SUBSCRIPTION_METHOD_LICENSE,
            'license_max_mikrotik' => 7,
            'license_max_ppp_users' => 300,
            'trial_days_remaining' => 21,
            'vpn_enabled' => false,
        ])
        ->assertRedirect(route('super-admin.tenants.show', $tenant));

    $tenant->refresh();

    expect($tenant->subscription_method)->toBe(User::SUBSCRIPTION_METHOD_LICENSE)
        ->and($tenant->subscription_status)->toBe('active')
        ->and($tenant->trial_days_remaining)->toBe(0)
        ->and($tenant->subscription_expires_at?->toDateString())
        ->toBe(now()->addDays(User::LICENSE_DURATION_DAYS)->toDateString());
});

it('clears license limits when tenant method switched back to monthly', function () {
    $superAdmin = createSuperAdmin();
    $plan = createPlan();
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'active',
        'subscription_plan_id' => $plan->id,
        'subscription_method' => User::SUBSCRIPTION_METHOD_LICENSE,
        'license_max_mikrotik' => 10,
        'license_max_ppp_users' => 400,
    ]);

    $this->actingAs($superAdmin)
        ->put(route('super-admin.tenants.update', $tenant), [
            'name' => $tenant->name,
            'email' => $tenant->email,
            'subscription_status' => 'active',
            'subscription_plan_id' => $plan->id,
            'subscription_method' => User::SUBSCRIPTION_METHOD_MONTHLY,
            'trial_days_remaining' => 0,
            'vpn_enabled' => false,
        ])
        ->assertRedirect(route('super-admin.tenants.show', $tenant));

    $tenant->refresh();

    expect($tenant->subscription_method)->toBe(User::SUBSCRIPTION_METHOD_MONTHLY)
        ->and($tenant->license_max_mikrotik)->toBeNull()
        ->and($tenant->license_max_ppp_users)->toBeNull();
});

it('activates license tenant with fixed annual duration', function () {
    $superAdmin = createSuperAdmin();
    $plan = createPlan([
        'duration_days' => 30,
    ]);
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_status' => 'expired',
        'subscription_method' => User::SUBSCRIPTION_METHOD_LICENSE,
    ]);

    $this->actingAs($superAdmin)
        ->post(route('super-admin.tenants.activate', $tenant), [
            'plan_id' => $plan->id,
            'duration_days' => 30,
        ])
        ->assertRedirect();

    $tenant->refresh();

    expect($tenant->subscription_expires_at?->toDateString())
        ->toBe(now()->addDays(User::LICENSE_DURATION_DAYS)->toDateString());

    $latestSubscription = Subscription::query()
        ->where('user_id', $tenant->id)
        ->latest('id')
        ->first();

    expect($latestSubscription)->not->toBeNull()
        ->and($latestSubscription->end_date->toDateString())
        ->toBe(now()->addDays(User::LICENSE_DURATION_DAYS)->toDateString());
});

it('uses plan limits for monthly and custom limits for license method', function () {
    $plan = createPlan([
        'max_mikrotik' => 4,
        'max_ppp_users' => 120,
    ]);

    $tenant = User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => false,
        'subscription_plan_id' => $plan->id,
        'subscription_method' => User::SUBSCRIPTION_METHOD_MONTHLY,
    ]);

    expect($tenant->getEffectiveMikrotikLimit())->toBe(4)
        ->and($tenant->getEffectivePppUsersLimit())->toBe(120);

    $tenant->update([
        'subscription_method' => User::SUBSCRIPTION_METHOD_LICENSE,
        'license_max_mikrotik' => 15,
        'license_max_ppp_users' => 600,
    ]);

    $tenant->refresh();

    expect($tenant->getEffectiveMikrotikLimit())->toBe(15)
        ->and($tenant->getEffectivePppUsersLimit())->toBe(600);
});
