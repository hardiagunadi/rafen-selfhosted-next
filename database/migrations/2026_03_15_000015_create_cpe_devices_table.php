<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpe_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ppp_user_id')->constrained('ppp_users')->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

            // GenieACS device identifier (the device's _id in MongoDB)
            $table->string('genieacs_device_id')->nullable();

            // Device info (cached from GenieACS)
            $table->string('serial_number')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('firmware_version')->nullable();

            // online / offline / unknown
            $table->string('status')->nullable()->default('unknown');

            $table->timestamp('last_seen_at')->nullable();

            // Cached parameters (WiFi SSID, PPPoE username, uptime, etc.)
            $table->json('cached_params')->nullable();

            $table->timestamps();

            $table->unique('ppp_user_id');
            $table->index(['owner_id', 'status']);
            $table->index('genieacs_device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpe_devices');
    }
};
