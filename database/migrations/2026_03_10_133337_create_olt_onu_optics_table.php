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
        Schema::create('olt_onu_optics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olt_connection_id')->constrained('olt_connections')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('onu_index');
            $table->string('pon_interface')->nullable();
            $table->string('onu_number')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('onu_name')->nullable();
            $table->decimal('rx_onu_dbm', 8, 2)->nullable();
            $table->decimal('tx_onu_dbm', 8, 2)->nullable();
            $table->decimal('rx_olt_dbm', 8, 2)->nullable();
            $table->decimal('tx_olt_dbm', 8, 2)->nullable();
            $table->string('status')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['olt_connection_id', 'onu_index'], 'olt_onu_optics_unique_index');
            $table->index(['owner_id', 'olt_connection_id'], 'olt_onu_optics_owner_connection_idx');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('olt_onu_optics');
    }
};
