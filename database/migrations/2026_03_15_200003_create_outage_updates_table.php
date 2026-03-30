<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outage_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outage_id')->constrained('outages')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['created', 'status_change', 'note', 'resolved', 'assigned'])->default('note');
            $table->text('body')->nullable();
            $table->string('meta', 255)->nullable();
            $table->string('image_path', 255)->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->index(['outage_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outage_updates');
    }
};
