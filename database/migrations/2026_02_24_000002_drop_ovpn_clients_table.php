<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ovpn_clients');
    }

    public function down(): void
    {
        Schema::create('ovpn_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mikrotik_connection_id')
                  ->nullable()
                  ->unique()
                  ->constrained()
                  ->cascadeOnDelete();
            $table->string('name');
            $table->string('common_name')->unique();
            $table->string('username')->nullable()->unique();
            $table->string('password')->nullable()->unique();
            $table->string('vpn_ip')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }
};
