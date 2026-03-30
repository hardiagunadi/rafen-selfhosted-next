<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('radius_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mikrotik_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username')->unique();
            $table->string('password');
            $table->enum('service', ['pppoe', 'hotspot']);
            $table->string('ipv4_address')->nullable();
            $table->string('rate_limit')->nullable();
            $table->string('profile')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['service', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('radius_accounts');
    }
};
