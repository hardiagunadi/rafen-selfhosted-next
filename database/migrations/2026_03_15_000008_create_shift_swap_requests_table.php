<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('from_schedule_id')->constrained('shift_schedules')->cascadeOnDelete();
            $table->foreignId('to_schedule_id')->nullable()->constrained('shift_schedules')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
            $table->index(['requester_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_swap_requests');
    }
};
