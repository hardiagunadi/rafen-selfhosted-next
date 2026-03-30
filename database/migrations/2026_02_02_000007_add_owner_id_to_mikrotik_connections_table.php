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
        Schema::table('mikrotik_connections', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->index('owner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mikrotik_connections', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropIndex(['owner_id']);
            $table->dropColumn('owner_id');
        });
    }
};
