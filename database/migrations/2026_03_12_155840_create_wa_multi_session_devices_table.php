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
        Schema::create('wa_multi_session_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_id', 150)->unique();
            $table->string('device_name', 120);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('last_status', 60)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
            $table->unique(['user_id', 'device_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_multi_session_devices');
    }
};
