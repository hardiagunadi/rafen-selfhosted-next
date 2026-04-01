<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_id', 150)->nullable();
            $table->string('contact_phone', 30)->index();
            $table->string('contact_name', 150)->nullable();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['open', 'pending', 'resolved'])->default('open')->index();
            $table->timestamp('bot_paused_until')->nullable();
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();

            $table->unique(['owner_id', 'contact_phone']);
            $table->index(['owner_id', 'status', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_conversations');
    }
};
