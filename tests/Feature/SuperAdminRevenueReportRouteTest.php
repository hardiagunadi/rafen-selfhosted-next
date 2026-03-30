<?php

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders super admin revenue report without sql ambiguity errors', function () {
    $superAdmin = User::factory()->create([
        'role' => 'administrator',
        'is_super_admin' => true,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $plan = SubscriptionPlan::query()->create([
        'name' => 'Paket Bisnis',
        'slug' => 'paket-bisnis',
        'price' => 200000,
        'duration_days' => 30,
        'max_mikrotik' => 10,
        'max_ppp_users' => 1000,
        'is_active' => true,
    ]);

    $subscription = Subscription::query()->create([
        'user_id' => $tenant->id,
        'subscription_plan_id' => $plan->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'active',
        'amount_paid' => 200000,
    ]);

    Payment::query()->create([
        'payment_number' => 'PAY-REV-001',
        'payment_type' => 'subscription',
        'user_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        'amount' => 200000,
        'fee' => 0,
        'total_amount' => 200000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->actingAs($superAdmin)
        ->get(route('super-admin.reports.revenue'))
        ->assertSuccessful();
});
