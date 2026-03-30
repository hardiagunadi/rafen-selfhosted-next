<?php

use App\Models\Invoice;
use App\Models\PppUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows teknisi income today from own cash renewals only', function () {
    $owner = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $teknisi = User::factory()->create([
        'parent_id' => $owner->id,
        'role' => 'teknisi',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $teknisiLain = User::factory()->create([
        'parent_id' => $owner->id,
        'role' => 'teknisi',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $pppUser = PppUser::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => 'CUST-001',
        'customer_name' => 'Pelanggan A',
        'username' => 'cust-a',
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-TECH-CASH-001',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $owner->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket A',
        'total' => 100000,
        'status' => 'paid',
        'paid_at' => now(),
        'paid_by' => $teknisi->id,
        'cash_received' => 100000,
        'payment_method' => 'cash',
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-TECH-TRANSFER-001',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $owner->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket B',
        'total' => 200000,
        'status' => 'paid',
        'paid_at' => now(),
        'paid_by' => $teknisi->id,
        'cash_received' => null,
        'payment_method' => 'transfer',
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-TECH-OTHER-001',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $owner->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket C',
        'total' => 300000,
        'status' => 'paid',
        'paid_at' => now(),
        'paid_by' => $teknisiLain->id,
        'cash_received' => 300000,
        'payment_method' => 'cash',
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-TECH-YESTERDAY-001',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $owner->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket D',
        'total' => 400000,
        'status' => 'paid',
        'paid_at' => now()->subDay(),
        'paid_by' => $teknisi->id,
        'cash_received' => 400000,
        'payment_method' => 'cash',
    ]);

    $this->actingAs($teknisi)
        ->get(route('dashboard'))
        ->assertDontSee('Ringkasan Operasional')
        ->assertDontSee('Dashboard Operasional ISP')
        ->assertDontSee('Monitor kesehatan jaringan, sesi aktif pelanggan, dan performa billing harian dalam layout yang lebih cepat dibaca.')
        ->assertDontSee('Waktu Server')
        ->assertDontSee('Health Router')
        ->assertSee('data-network-performance-layout="full"', false)
        ->assertSee(route('invoices.unpaid'), false)
        ->assertSuccessful()
        ->assertViewHas('stats', function (array $stats): bool {
            return (float) ($stats['income_today'] ?? 0) === 100000.0;
        });
});
