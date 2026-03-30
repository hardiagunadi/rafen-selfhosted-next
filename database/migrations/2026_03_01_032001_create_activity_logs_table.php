<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->string('action', 50);
            $table->string('subject_type', 50);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['owner_id', 'created_at']);
            $table->index('action');
            $table->index('subject_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
