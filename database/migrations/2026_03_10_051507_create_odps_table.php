<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 120);
            $table->string('name', 150);
            $table->string('area', 150)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('capacity_ports')->default(0);
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['owner_id', 'code']);
            $table->index(['owner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odps');
    }
};
