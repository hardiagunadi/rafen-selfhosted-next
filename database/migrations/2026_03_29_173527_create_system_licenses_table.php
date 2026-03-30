<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('missing');
            $table->string('license_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('instance_name')->nullable();
            $table->string('fingerprint')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->date('support_until')->nullable();
            $table->unsignedInteger('grace_days')->default(21);
            $table->json('domains')->nullable();
            $table->json('modules')->nullable();
            $table->json('limits')->nullable();
            $table->json('payload')->nullable();
            $table->text('validation_error')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('restricted_mode_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_licenses');
    }
};
