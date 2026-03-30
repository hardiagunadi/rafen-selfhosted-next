<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotspot_users', function (Blueprint $table) {
            $table->id();
            $table->enum('status_registrasi', ['aktif', 'on_process'])->default('aktif');
            $table->enum('tipe_pembayaran', ['prepaid', 'postpaid'])->default('prepaid');
            $table->enum('status_bayar', ['sudah_bayar', 'belum_bayar'])->default('belum_bayar');
            $table->enum('status_akun', ['enable', 'disable', 'isolir'])->default('enable');
            $table->foreignId('owner_id')->constrained('users');
            $table->foreignId('hotspot_profile_id')->nullable()->constrained('hotspot_profiles')->nullOnDelete();
            $table->foreignId('profile_group_id')->nullable()->constrained('profile_groups')->nullOnDelete();
            $table->boolean('tagihkan_ppn')->default(false);
            $table->decimal('biaya_instalasi', 12, 2)->default(0);
            $table->date('jatuh_tempo')->nullable();
            $table->enum('aksi_jatuh_tempo', ['isolir', 'tetap_terhubung'])->default('isolir');
            $table->string('customer_id')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('nik')->nullable();
            $table->string('nomor_hp')->nullable();
            $table->string('email')->nullable();
            $table->text('alamat')->nullable();
            $table->string('username')->nullable()->unique();
            $table->string('hotspot_password')->nullable();
            $table->text('catatan')->nullable();
            $table->string('mixradius_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotspot_users');
    }
};
