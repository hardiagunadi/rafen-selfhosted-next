<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('radcheck')) {
            // Remove duplicate rows before adding unique index.
            DB::table('radcheck')
                ->whereNotIn('id', function ($query) {
                    $query->fromSub(
                        DB::table('radcheck')
                            ->selectRaw('MIN(id) as min_id')
                            ->groupBy('username', 'attribute'),
                        'uniq_rows'
                    )->select('min_id');
                })
                ->delete();

            Schema::table('radcheck', function (Blueprint $table) {
                $table->unique(['username', 'attribute'], 'radcheck_username_attribute_unique');
            });
        }

        if (Schema::hasTable('radreply')) {
            DB::table('radreply')
                ->whereNotIn('id', function ($query) {
                    $query->fromSub(
                        DB::table('radreply')
                            ->selectRaw('MIN(id) as min_id')
                            ->groupBy('username', 'attribute'),
                        'uniq_rows'
                    )->select('min_id');
                })
                ->delete();

            Schema::table('radreply', function (Blueprint $table) {
                $table->unique(['username', 'attribute'], 'radreply_username_attribute_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('radcheck')) {
            Schema::table('radcheck', function (Blueprint $table) {
                $table->dropUnique('radcheck_username_attribute_unique');
            });
        }

        if (Schema::hasTable('radreply')) {
            Schema::table('radreply', function (Blueprint $table) {
                $table->dropUnique('radreply_username_attribute_unique');
            });
        }
    }
};
