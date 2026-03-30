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
        Schema::create('hotspot_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('owner_id')->constrained('users');
            $table->decimal('harga_jual', 12, 2)->default(0);
            $table->decimal('harga_promo', 12, 2)->default(0);
            $table->decimal('ppn', 5, 2)->default(0);
            $table->foreignId('bandwidth_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('profile_type', ['unlimited', 'limited'])->default('unlimited');
            $table->enum('limit_type', ['time', 'quota'])->nullable();
            $table->unsignedInteger('time_limit_value')->nullable();
            $table->enum('time_limit_unit', ['menit', 'jam', 'hari', 'bulan'])->nullable();
            $table->decimal('quota_limit_value', 12, 2)->unsigned()->nullable();
            $table->enum('quota_limit_unit', ['mb', 'gb'])->nullable();
            $table->unsignedInteger('masa_aktif_value')->nullable();
            $table->enum('masa_aktif_unit', ['menit', 'jam', 'hari', 'bulan'])->nullable();
            $table->foreignId('profile_group_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('shared_users')->default(1);
            $table->enum('prioritas', ['default', 'prioritas1', 'prioritas2', 'prioritas3', 'prioritas4', 'prioritas5', 'prioritas6', 'prioritas7', 'prioritas8'])->default('default');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotspot_profiles');
    }
};
