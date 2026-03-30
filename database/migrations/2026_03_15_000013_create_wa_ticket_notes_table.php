<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_ticket_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('wa_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->string('image_path')->nullable();
            $table->string('type', 30)->default('note'); // note | status_change | assigned | created
            $table->string('meta', 150)->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_ticket_notes');
    }
};
