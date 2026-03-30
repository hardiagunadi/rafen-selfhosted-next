<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique()
                ->comment('WD-YYYYMMDD-XXXXXX format');
            $table->unsignedBigInteger('owner_id')->index()
                ->comment('Tenant admin who requested withdrawal');
            $table->decimal('amount', 15, 2)
                ->comment('Amount requested to withdraw');
            $table->enum('status', ['pending', 'approved', 'rejected', 'settled'])
                ->default('pending');
            // Bank destination details — snapshot at request time
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_name')->nullable();
            // Admin processing fields
            $table->unsignedBigInteger('processed_by')->nullable()
                ->comment('Super admin user ID who processed the request');
            $table->timestamp('processed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            // Settlement proof
            $table->string('transfer_proof')->nullable()
                ->comment('Storage path to transfer receipt image');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
    }
};
