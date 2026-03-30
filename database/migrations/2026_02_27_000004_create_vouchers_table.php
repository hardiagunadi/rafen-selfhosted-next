<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
            $table->foreignId('hotspot_profile_id')->nullable()->constrained('hotspot_profiles')->nullOnDelete();
            $table->foreignId('profile_group_id')->nullable()->constrained('profile_groups')->nullOnDelete();
            $table->string('batch_name')->nullable()->index();
            $table->string('code')->unique();
            $table->enum('status', ['unused', 'used', 'expired'])->default('unused');
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->string('used_by_mac')->nullable();
            $table->string('used_by_ip')->nullable();
            $table->string('mixradius_id')->nullable()->index();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
            $table->index(['status', 'expired_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
