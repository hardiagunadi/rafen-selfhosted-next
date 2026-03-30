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
        Schema::create('wa_multi_session_auth_store', function (Blueprint $table) {
            $table->string('id', 191);
            $table->string('session_id', 191);
            $table->string('category', 120)->nullable();
            $table->longText('value')->nullable();
            $table->timestamps();

            $table->primary(['id', 'session_id']);
            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_multi_session_auth_store');
    }
};
