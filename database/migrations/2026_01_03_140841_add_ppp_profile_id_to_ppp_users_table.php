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
        Schema::table('ppp_users', function (Blueprint $table) {
            $table->foreignId('ppp_profile_id')
                ->nullable()
                ->after('owner_id')
                ->constrained('ppp_profiles')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ppp_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ppp_profile_id');
        });
    }
};
