<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shift_definition_id')->constrained('shift_definitions')->cascadeOnDelete();
            $table->date('schedule_date')->index();
            $table->enum('status', ['scheduled', 'confirmed', 'swapped', 'cancelled'])->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'shift_definition_id', 'schedule_date']);
            $table->index(['owner_id', 'schedule_date']);
            $table->index(['user_id', 'schedule_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_schedules');
    }
};
