<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bandwidth_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable()->after('owner');
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bandwidth_profiles', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });
    }
};
