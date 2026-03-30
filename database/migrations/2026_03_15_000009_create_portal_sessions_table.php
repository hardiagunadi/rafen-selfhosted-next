<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ppp_user_id')->constrained('ppp_users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('expires_at');

            $table->index(['ppp_user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_sessions');
    }
};
