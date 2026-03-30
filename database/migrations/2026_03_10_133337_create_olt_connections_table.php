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
        Schema::create('olt_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->enum('vendor', ['hsgq'])->default('hsgq');
            $table->string('name');
            $table->string('host', 191);
            $table->unsignedSmallInteger('snmp_port')->default(161);
            $table->enum('snmp_version', ['2c'])->default('2c');
            $table->string('snmp_community');
            $table->unsignedTinyInteger('snmp_timeout')->default(5);
            $table->unsignedTinyInteger('snmp_retries')->default(1);
            $table->boolean('is_active')->default(true);
            $table->string('oid_serial')->nullable();
            $table->string('oid_onu_name')->nullable();
            $table->string('oid_rx_onu')->nullable();
            $table->string('oid_tx_onu')->nullable();
            $table->string('oid_rx_olt')->nullable();
            $table->string('oid_tx_olt')->nullable();
            $table->string('oid_status')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->boolean('last_poll_success')->nullable();
            $table->string('last_poll_message', 255)->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'is_active']);
            $table->unique(['owner_id', 'name'], 'olt_connections_owner_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('olt_connections');
    }
};
