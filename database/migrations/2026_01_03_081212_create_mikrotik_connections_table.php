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
        Schema::create('mikrotik_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('api_port')->default(8728);
            $table->unsignedSmallInteger('api_ssl_port')->default(8729);
            $table->boolean('use_ssl')->default(false);
            $table->string('username');
            $table->string('password');
            $table->string('radius_secret')->nullable();
            $table->string('ros_version')->default('7');
            $table->unsignedTinyInteger('api_timeout')->default(10);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mikrotik_connections');
    }
};
