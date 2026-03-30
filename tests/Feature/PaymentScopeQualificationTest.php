<?php

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps payment scopes unambiguous on join queries', function () {
    $tenant = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $plan = SubscriptionPlan::query()->create([
        'name' => 'Paket Pro',
        'slug' => 'paket-pro',
        'price' => 150000,
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
        'amount_paid' => 150000,
    ]);

    Payment::query()->create([
        'payment_number' => 'PAY-SCOPE-001',
        'payment_type' => 'subscription',
        'user_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        'amount' => 150000,
        'fee' => 0,
        'total_amount' => 150000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $rows = Payment::paid()
        ->forSubscription()
        ->whereBetween('payments.paid_at', [now()->startOfMonth(), now()->endOfMonth()])
        ->join('subscriptions', 'payments.subscription_id', '=', 'subscriptions.id')
        ->join('subscription_plans', 'subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
        ->selectRaw('subscription_plans.name as plan_name, SUM(payments.amount) as total')
        ->groupBy('subscription_plans.id', 'subscription_plans.name')
        ->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->plan_name)->toBe('Paket Pro')
        ->and((float) $rows->first()->total)->toBe(150000.0);
});
