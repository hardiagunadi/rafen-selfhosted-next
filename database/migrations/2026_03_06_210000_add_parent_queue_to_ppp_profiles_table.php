<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppp_profiles', function (Blueprint $table) {
            $table->string('parent_queue')->nullable()->after('bandwidth_profile_id');
        });
    }

    public function down(): void
    {
        Schema::table('ppp_profiles', function (Blueprint $table) {
            $table->dropColumn('parent_queue');
        });
    }
};
