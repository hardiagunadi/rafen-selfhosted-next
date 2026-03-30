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
        if (! Schema::hasTable('wa_conversations')) {
            return;
        }

        if (Schema::hasColumn('wa_conversations', 'bot_paused_until')) {
            return;
        }

        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->timestamp('bot_paused_until')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('wa_conversations')) {
            return;
        }

        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->dropColumn('bot_paused_until');
        });
    }
};
