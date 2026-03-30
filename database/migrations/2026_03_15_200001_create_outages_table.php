<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('status', ['open', 'in_progress', 'resolved'])->default('open');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->timestamp('started_at');
            $table->timestamp('estimated_resolved_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('assigned_teknisi_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('public_token', 64)->unique();
            $table->timestamp('wa_blast_sent_at')->nullable();
            $table->unsignedInteger('wa_blast_count')->default(0);
            $table->boolean('include_status_link')->default(true);
            $table->timestamp('resolution_wa_sent_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
            $table->index(['assigned_teknisi_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outages');
    }
};
