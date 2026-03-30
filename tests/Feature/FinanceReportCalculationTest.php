<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PppUser;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function financeAdmin(): User
{
    return User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

function financePppUser(User $owner): PppUser
{
    return PppUser::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => 'CUST-'.fake()->numerify('#####'),
        'customer_name' => fake()->name(),
        'username' => 'user-'.fake()->unique()->numerify('#####'),
    ]);
}

it('calculates daily income from customer invoices and voucher transactions', function () {
    $admin = financeAdmin();
    $pppUser = financePppUser($admin);

    Invoice::query()->create([
        'invoice_number' => 'INV-DLY-001',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Harian',
        'total' => 100000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    Transaction::query()->create([
        'owner_id' => $admin->id,
        'type' => 'voucher',
        'total' => 50000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('reports.income', [
            'report' => 'daily',
            'date' => now()->toDateString(),
            'tipe_user' => 'semua',
        ]))
        ->assertSuccessful()
        ->assertViewHas('report', function (array $report): bool {
            return (float) ($report['summary']['total_income'] ?? 0) === 150000.0
                && (float) ($report['summary']['customer_income'] ?? 0) === 100000.0
                && (float) ($report['summary']['voucher_income'] ?? 0) === 50000.0
                && $report['items']->count() === 2;
        });
});

it('calculates bhp uso using configurable deductions and rates', function () {
    $admin = financeAdmin();
    $pppUser = financePppUser($admin);

    Invoice::query()->create([
        'invoice_number' => 'INV-BHP-001',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket BHP',
        'total' => 1000000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('reports.income', [
            'report' => 'bhp_uso',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'bad_debt_deduction' => 100000,
            'interconnection_deduction' => 200000,
            'bhp_rate' => 0.5,
            'uso_rate' => 1.25,
        ]))
        ->assertSuccessful()
        ->assertViewHas('report', function (array $report): bool {
            return (float) ($report['summary']['gross_revenue'] ?? 0) === 1000000.0
                && (float) ($report['summary']['revenue_basis'] ?? 0) === 700000.0
                && (float) ($report['summary']['bhp_amount'] ?? 0) === 3500.0
                && (float) ($report['summary']['uso_amount'] ?? 0) === 8750.0
                && (float) ($report['summary']['total_obligation'] ?? 0) === 12250.0;
        });
});

it('calculates profit loss with gateway expense and bhp uso expense components', function () {
    $admin = financeAdmin();
    $pppUser = financePppUser($admin);

    $invoice = Invoice::query()->create([
        'invoice_number' => 'INV-PL-001',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Laba Rugi',
        'total' => 800000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    Payment::query()->create([
        'payment_number' => 'PAY-PL-001',
        'payment_type' => 'invoice',
        'user_id' => $admin->id,
        'invoice_id' => $invoice->id,
        'amount' => 800000,
        'fee' => 10000,
        'total_amount' => 810000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('reports.income', [
            'report' => 'profit_loss',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'bhp_rate' => 0.5,
            'uso_rate' => 1.25,
        ]))
        ->assertSuccessful()
        ->assertViewHas('report', function (array $report): bool {
            return (float) ($report['summary']['gross_revenue'] ?? 0) === 800000.0
                && (float) ($report['summary']['gateway_expense'] ?? 0) === 10000.0
                && (float) ($report['summary']['bhp_amount'] ?? 0) === 4000.0
                && (float) ($report['summary']['uso_amount'] ?? 0) === 10000.0
                && (float) ($report['summary']['total_expense'] ?? 0) === 24000.0
                && (float) ($report['summary']['net_profit'] ?? 0) === 776000.0;
        });
});

it('limits teknisi income report to invoices paid by the same teknisi only', function () {
    $admin = financeAdmin();
    $teknisiA = User::factory()->create([
        'parent_id' => $admin->id,
        'role' => 'teknisi',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
    $teknisiB = User::factory()->create([
        'parent_id' => $admin->id,
        'role' => 'teknisi',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
    $pppUser = financePppUser($admin);

    Invoice::query()->create([
        'invoice_number' => 'INV-TEK-A-001',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Teknisi A',
        'total' => 100000,
        'status' => 'paid',
        'paid_at' => now(),
        'paid_by' => $teknisiA->id,
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-TEK-B-001',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $admin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Teknisi B',
        'total' => 200000,
        'status' => 'paid',
        'paid_at' => now(),
        'paid_by' => $teknisiB->id,
    ]);

    Transaction::query()->create([
        'owner_id' => $admin->id,
        'type' => 'voucher',
        'total' => 50000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->actingAs($teknisiA)
        ->get(route('reports.income', [
            'report' => 'daily',
            'date' => now()->toDateString(),
            'tipe_user' => 'semua',
        ]))
        ->assertSuccessful()
        ->assertViewHas('report', function (array $report): bool {
            return (float) ($report['summary']['total_income'] ?? 0) === 100000.0
                && (float) ($report['summary']['customer_income'] ?? 0) === 100000.0
                && (float) ($report['summary']['voucher_income'] ?? 0) === 0.0
                && $report['items']->count() === 1
                && ($report['items']->first()['reference'] ?? null) === 'INV-TEK-A-001';
        });
});
