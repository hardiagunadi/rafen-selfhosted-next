<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_conversations') && ! Schema::hasColumn('wa_conversations', 'bot_paused_until')) {
            Schema::table('wa_conversations', function (Blueprint $table) {
                $table->timestamp('bot_paused_until')->nullable()->after('status');
            });
        }

        if (! Schema::hasTable('wa_conversation_states') || ! Schema::hasTable('wa_conversations')) {
            return;
        }

        if (! $this->foreignKeyExists('wa_conversation_states', 'wa_conversation_states_conversation_id_foreign')) {
            Schema::table('wa_conversation_states', function (Blueprint $table) {
                $table->foreign('conversation_id')->references('id')->on('wa_conversations')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('wa_conversation_states')) {
            return;
        }

        if ($this->foreignKeyExists('wa_conversation_states', 'wa_conversation_states_conversation_id_foreign')) {
            Schema::table('wa_conversation_states', function (Blueprint $table) {
                $table->dropForeign('wa_conversation_states_conversation_id_foreign');
            });
        }
    }

    private function foreignKeyExists(string $table, string $foreignKeyName): bool
    {
        $driver = DB::connection()->getDriverName();
        $database = DB::getDatabaseName();
        $tableName = DB::getTablePrefix().$table;

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $result = DB::selectOne(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? LIMIT 1',
                [$database, $tableName, $foreignKeyName]
            );

            return $result !== null;
        }

        if ($driver === 'pgsql') {
            $result = DB::selectOne(
                'SELECT conname FROM pg_constraint WHERE conname = ? LIMIT 1',
                [$foreignKeyName]
            );

            return $result !== null;
        }

        if ($driver === 'sqlite') {
            return false;
        }

        return false;
    }
};
