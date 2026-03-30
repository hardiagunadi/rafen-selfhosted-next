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
            $table->foreignId('assigned_teknisi_id')->nullable()->after('owner_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ppp_users', function (Blueprint $table) {
            $table->dropForeign(['assigned_teknisi_id']);
            $table->dropColumn('assigned_teknisi_id');
        });
    }
};
