<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('wa_conversations')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('type', ['complaint', 'installation', 'troubleshoot', 'other'])->default('other');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'status', 'priority']);
            $table->index(['assigned_to_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_tickets');
    }
};
