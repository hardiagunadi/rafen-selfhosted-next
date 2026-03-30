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
        Schema::create('wa_keyword_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->json('keywords');
            $table->text('reply_text');
            $table->unsignedTinyInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['owner_id', 'is_active', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_keyword_rules');
    }
};
