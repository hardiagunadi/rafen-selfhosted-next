<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_blast_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->string('event', 50)->index();        // blast, registration, invoice_created, invoice_paid
            $table->string('phone', 30)->nullable();
            $table->string('phone_normalized', 30)->nullable();
            $table->string('status', 20)->index();       // sent, skip, failed
            $table->string('reason')->nullable();        // alasan skip/gagal
            $table->string('invoice_number', 50)->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // ppp/hotspot user
            $table->string('username', 100)->nullable();
            $table->string('customer_name', 150)->nullable();
            $table->string('ref_id', 100)->nullable();   // ref_id dari gateway
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_blast_logs');
    }
};
