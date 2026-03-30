<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outage_affected_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outage_id')->constrained('outages')->cascadeOnDelete();
            $table->enum('area_type', ['odp', 'keyword'])->default('odp');
            $table->foreignId('odp_id')->nullable()->constrained('odps')->nullOnDelete();
            $table->string('label', 255)->nullable();
            $table->timestamps();

            $table->index('outage_id');
            $table->index('odp_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outage_affected_areas');
    }
};
