<?php

use App\Models\HotspotProfile;
use App\Models\User;
use App\Models\Voucher;
use App\Services\VoucherUsageTracker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('marks unused voucher as used from radacct history even when session already stopped', function () {
    if (! Schema::hasTable('radacct')) {
        Schema::create('radacct', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->dateTime('acctstarttime')->nullable();
            $table->dateTime('acctstoptime')->nullable();
        });
    }

    $owner = User::factory()->create();
    $profile = HotspotProfile::factory()->create([
        'owner_id' => $owner->id,
        'profile_group_id' => null,
        'masa_aktif_value' => 2,
        'masa_aktif_unit' => 'jam',
    ]);

    $voucher = Voucher::query()->create([
        'owner_id' => $owner->id,
        'hotspot_profile_id' => $profile->id,
        'code' => 'VOU-USED-HISTORY',
        'status' => 'unused',
        'username' => 'voucher-history',
        'password' => 'voucher-history',
    ]);

    DB::table('radacct')->insert([
        'username' => 'voucher-history',
        'acctstarttime' => now()->subHours(3),
        'acctstoptime' => now()->subHours(2),
    ]);

    $count = app(VoucherUsageTracker::class)->markUsedFromRadacct();
    $voucher->refresh();

    expect($count)->toBe(1)
        ->and($voucher->status)->toBe('used')
        ->and($voucher->used_at)->not->toBeNull()
        ->and($voucher->expired_at)->not->toBeNull()
        ->and($voucher->expired_at->equalTo($voucher->used_at->copy()->addHours(2)))->toBeTrue();
});

it('deletes expired vouchers and clears radius rows', function () {
    if (! Schema::hasTable('radcheck')) {
        Schema::create('radcheck', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username');
            $table->string('attribute');
            $table->string('op', 2)->default(':=');
            $table->text('value')->nullable();
        });
    }

    if (! Schema::hasTable('radreply')) {
        Schema::create('radreply', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username');
            $table->string('attribute');
            $table->string('op', 2)->default(':=');
            $table->text('value')->nullable();
        });
    }

    $owner = User::factory()->create();
    $profile = HotspotProfile::factory()->create([
        'owner_id' => $owner->id,
        'profile_group_id' => null,
    ]);

    $voucher = Voucher::query()->create([
        'owner_id' => $owner->id,
        'hotspot_profile_id' => $profile->id,
        'code' => 'VOU-EXPIRED-DELETE',
        'status' => 'used',
        'username' => 'voucher-expired',
        'password' => 'voucher-expired',
        'used_at' => now()->subDays(2),
        'expired_at' => now()->subHour(),
    ]);

    DB::table('radcheck')->insert([
        'username' => 'voucher-expired',
        'attribute' => 'Cleartext-Password',
        'op' => ':=',
        'value' => 'voucher-expired',
    ]);

    DB::table('radreply')->insert([
        'username' => 'voucher-expired',
        'attribute' => 'Mikrotik-Group',
        'op' => ':=',
        'value' => 'Hotspot',
    ]);

    $this->artisan('vouchers:expire')
        ->assertSuccessful();

    $this->assertDatabaseMissing('vouchers', ['id' => $voucher->id]);
    $this->assertDatabaseMissing('radcheck', ['username' => 'voucher-expired']);
    $this->assertDatabaseMissing('radreply', ['username' => 'voucher-expired']);
});
