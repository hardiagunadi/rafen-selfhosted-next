<?php

use App\Models\PppUser;
use App\Models\User;
use App\Services\IsolirSynchronizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('sets queue parent dan rate limit isolir saat user diisolir', function () {
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

    $owner = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $pppUser = PppUser::query()->create([
        'owner_id' => $owner->id,
        'status_registrasi' => 'aktif',
        'status_akun' => 'isolir',
        'status_bayar' => 'belum_bayar',
        'aksi_jatuh_tempo' => 'isolir',
        'customer_id' => '000000009999',
        'customer_name' => 'Uji Isolir',
        'username' => 'uji-isolir',
        'ppp_password' => 'secret-isolir',
    ]);

    DB::table('radreply')->insert([
        ['username' => $pppUser->username, 'attribute' => 'Mikrotik-Queue-Parent-Name', 'op' => ':=', 'value' => '0. PPPOe Pelanggan'],
        ['username' => $pppUser->username, 'attribute' => 'Mikrotik-Rate-Limit', 'op' => ':=', 'value' => '10M/10M'],
    ]);

    app(IsolirSynchronizer::class)->isolate($pppUser);

    $this->assertDatabaseHas('radreply', [
        'username' => $pppUser->username,
        'attribute' => 'Mikrotik-Group',
        'value' => 'isolir-pppoe',
    ]);

    $this->assertDatabaseHas('radreply', [
        'username' => $pppUser->username,
        'attribute' => 'Framed-Pool',
        'value' => 'pool-isolir',
    ]);

    $this->assertDatabaseHas('radreply', [
        'username' => $pppUser->username,
        'attribute' => 'Mikrotik-Queue-Parent-Name',
        'value' => '2. Expired User',
    ]);

    $this->assertDatabaseHas('radreply', [
        'username' => $pppUser->username,
        'attribute' => 'Mikrotik-Rate-Limit',
        'value' => '128k/128k',
    ]);

    $this->assertDatabaseHas('radcheck', [
        'username' => $pppUser->username,
        'attribute' => 'Cleartext-Password',
        'value' => 'secret-isolir',
    ]);
});
