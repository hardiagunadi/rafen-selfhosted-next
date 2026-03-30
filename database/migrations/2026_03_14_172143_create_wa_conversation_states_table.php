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
        Schema::create('wa_conversation_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->unique()->constrained('wa_conversations')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('flow', 60);
            $table->unsignedTinyInteger('step')->default(1);
            $table->json('collected')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['owner_id', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_conversation_states');
    }
};
