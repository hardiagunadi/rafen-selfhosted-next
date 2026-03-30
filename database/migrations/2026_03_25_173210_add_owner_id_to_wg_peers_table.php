<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wg_peers', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable()->after('id');
            $table->index('owner_id');
        });

        // Backfill: set owner_id dari mikrotik_connections.owner_id jika ada.
        // Gunakan query builder agar kompatibel lintas database (SQLite/MySQL/PostgreSQL).
        $peerOwners = DB::table('wg_peers')
            ->select('wg_peers.id', 'mikrotik_connections.owner_id')
            ->join('mikrotik_connections', 'mikrotik_connections.id', '=', 'wg_peers.mikrotik_connection_id')
            ->whereNotNull('wg_peers.mikrotik_connection_id')
            ->orderBy('wg_peers.id')
            ->cursor();

        foreach ($peerOwners as $peerOwner) {
            if (! isset($peerOwner->owner_id)) {
                continue;
            }

            DB::table('wg_peers')
                ->where('id', $peerOwner->id)
                ->update(['owner_id' => $peerOwner->owner_id]);
        }
    }

    public function down(): void
    {
        Schema::table('wg_peers', function (Blueprint $table) {
            $table->dropIndex(['owner_id']);
            $table->dropColumn('owner_id');
        });
    }
};
