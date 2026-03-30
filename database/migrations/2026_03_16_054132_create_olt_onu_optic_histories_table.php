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
        Schema::create('olt_onu_optic_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olt_onu_optic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('olt_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('rx_onu_dbm', 8, 2)->nullable();
            $table->decimal('tx_onu_dbm', 8, 2)->nullable();
            $table->decimal('rx_olt_dbm', 8, 2)->nullable();
            $table->integer('distance_m')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('polled_at');

            $table->index(['olt_onu_optic_id', 'polled_at']);
            $table->index(['olt_connection_id', 'polled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('olt_onu_optic_histories');
    }
};
