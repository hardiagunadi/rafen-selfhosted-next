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
        Schema::create('wa_platform_device_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete()
                ->comment('Tenant (user) yang mengajukan permintaan');
            $table->foreignId('device_id')->nullable()->constrained('wa_multi_session_devices')->nullOnDelete()
                ->comment('Device platform yang di-assign saat approved');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('reason')->nullable()->comment('Alasan permintaan dari tenant');
            $table->text('notes')->nullable()->comment('Catatan super admin saat approve/reject');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_platform_device_requests');
    }
};
