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
        Schema::create('bandwidth_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('upload_min_mbps')->default(0);
            $table->unsignedInteger('upload_max_mbps')->default(0);
            $table->unsignedInteger('download_min_mbps')->default(0);
            $table->unsignedInteger('download_max_mbps')->default(0);
            $table->string('owner')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bandwidth_profiles');
    }
};
