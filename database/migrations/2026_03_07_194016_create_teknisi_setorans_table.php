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
        Schema::create('teknisi_setorans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teknisi_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_date');
            $table->integer('total_invoices')->default(0);
            $table->decimal('total_tagihan', 12, 2)->default(0);
            $table->decimal('total_cash', 12, 2)->default(0);
            $table->enum('status', ['draft', 'submitted', 'verified'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['owner_id', 'teknisi_id', 'period_date']);
            $table->index(['teknisi_id', 'period_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teknisi_setorans');
    }
};
