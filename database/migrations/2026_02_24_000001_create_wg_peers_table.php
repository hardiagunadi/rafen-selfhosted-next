<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wg_peers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mikrotik_connection_id')
                  ->nullable()
                  ->unique()
                  ->constrained()
                  ->cascadeOnDelete();
            $table->string('name');
            $table->text('public_key')->unique();
            $table->text('private_key');
            $table->string('preshared_key')->nullable();
            $table->string('vpn_ip')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wg_peers');
    }
};
