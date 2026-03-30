<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_webhook_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('wa_webhook_logs', function (Blueprint $table) {
            $table->dropIndex(['owner_id']);
            $table->dropColumn('owner_id');
        });
    }
};
