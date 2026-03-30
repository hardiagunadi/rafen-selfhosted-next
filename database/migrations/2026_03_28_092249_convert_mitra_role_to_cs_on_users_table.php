<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        DB::table('users')
            ->where('role', 'mitra')
            ->update(['role' => 'cs']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
