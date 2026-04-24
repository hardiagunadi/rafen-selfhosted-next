<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ppp_user_id')->nullable()->constrained('ppp_users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note_type', 40)->index();
            $table->string('document_number', 120);
            $table->string('document_title', 150);
            $table->string('summary_title', 150);
            $table->string('service_type', 40)->default('general')->index();
            $table->string('status', 20)->default('paid')->index();
            $table->date('note_date')->index();
            $table->string('customer_id')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('customer_address')->nullable();
            $table->string('package_name')->nullable();
            $table->json('item_lines');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('payment_method', 40)->nullable();
            $table->json('transfer_accounts')->nullable();
            $table->boolean('show_service_section')->default(true);
            $table->decimal('cash_received', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->text('footer')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->unique(['owner_id', 'document_number']);
            $table->index(['owner_id', 'status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_notes');
    }
};
