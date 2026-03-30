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
        Schema::create('ppp_users', function (Blueprint $table) {
            $table->id();
            $table->enum('status_registrasi', ['aktif', 'on_process'])->default('aktif');
            $table->enum('tipe_pembayaran', ['prepaid', 'postpaid'])->default('prepaid');
            $table->enum('status_bayar', ['sudah_bayar', 'belum_bayar'])->default('belum_bayar');
            $table->enum('status_akun', ['enable', 'disable'])->default('enable');
            $table->foreignId('owner_id')->constrained('users');
            $table->enum('tipe_service', ['pppoe', 'l2tp_pptp', 'openvpn_sstp'])->default('pppoe');
            $table->boolean('tagihkan_ppn')->default(true);
            $table->boolean('prorata_otomatis')->default(false);
            $table->boolean('promo_aktif')->default(false);
            $table->unsignedInteger('durasi_promo_bulan')->default(0);
            $table->decimal('biaya_instalasi', 12, 2)->default(0);
            $table->date('jatuh_tempo')->nullable();
            $table->enum('aksi_jatuh_tempo', ['isolir', 'tetap_terhubung'])->default('isolir');
            $table->enum('tipe_ip', ['dhcp', 'static'])->default('dhcp');
            $table->foreignId('profile_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_static')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppp_users');
    }
};
