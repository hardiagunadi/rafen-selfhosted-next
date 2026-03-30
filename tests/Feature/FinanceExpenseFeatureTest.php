<?php

use App\Models\FinanceExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows administrator to store manual expense', function () {
    $admin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($admin)
        ->from(route('reports.income', ['report' => 'expense']))
        ->post(route('reports.expenses.store'), [
            'expense_date' => now()->toDateString(),
            'category' => 'Biaya Operasional',
            'service_type' => 'general',
            'amount' => 125000,
            'payment_method' => 'transfer',
            'reference' => 'NOTA-001',
            'description' => 'Pembelian perlengkapan teknisi',
        ])
        ->assertRedirect(route('reports.income', ['report' => 'expense']));

    $this->assertDatabaseHas('finance_expenses', [
        'owner_id' => $admin->id,
        'created_by' => $admin->id,
        'category' => 'Biaya Operasional',
        'service_type' => 'general',
        'amount' => 125000,
    ]);
});

it('forbids teknisi from storing manual expense', function () {
    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $teknisi = User::factory()->create([
        'parent_id' => $tenantAdmin->id,
        'role' => 'teknisi',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($teknisi)
        ->post(route('reports.expenses.store'), [
            'expense_date' => now()->toDateString(),
            'category' => 'Biaya Operasional',
            'service_type' => 'general',
            'amount' => 10000,
        ])
        ->assertForbidden();
});

it('includes manual expense in expense report summary', function () {
    $admin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    FinanceExpense::query()->create([
        'owner_id' => $admin->id,
        'created_by' => $admin->id,
        'expense_date' => now()->toDateString(),
        'category' => 'Biaya Lapangan',
        'service_type' => 'general',
        'amount' => 25000,
        'payment_method' => 'cash',
        'description' => 'Transport teknisi',
    ]);

    $this->actingAs($admin)
        ->get(route('reports.income', [
            'report' => 'expense',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]))
        ->assertSuccessful()
        ->assertViewHas('report', function (array $report): bool {
            return (float) ($report['summary']['manual_expense'] ?? 0) === 25000.0
                && (float) ($report['summary']['total_expense'] ?? 0) === 25000.0
                && $report['items']->contains(fn (array $item): bool => $item['expense_type'] === 'manual');
        });
});
