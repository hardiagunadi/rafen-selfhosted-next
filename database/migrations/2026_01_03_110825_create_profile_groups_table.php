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
        Schema::create('profile_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('owner')->nullable();
            $table->foreignId('mikrotik_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['hotspot', 'pppoe']);
            $table->enum('ip_pool_mode', ['group_only', 'sql']);
            $table->string('ip_pool_name')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('netmask')->nullable();
            $table->string('range_start')->nullable();
            $table->string('range_end')->nullable();
            $table->string('dns_servers')->nullable();
            $table->string('parent_queue')->nullable();
            $table->string('host_min')->nullable();
            $table->string('host_max')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_groups');
    }
};
