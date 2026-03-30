<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('renewed_without_payment')->default(false)->after('status');
        });

        DB::table('invoices')
            ->select(['id', 'created_at', 'updated_at'])
            ->where('status', 'unpaid')
            ->whereNull('paid_at')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                $renewedIds = [];

                foreach ($rows as $row) {
                    if (! $row->created_at || ! $row->updated_at) {
                        continue;
                    }

                    $createdAt = Carbon::parse((string) $row->created_at);
                    $updatedAt = Carbon::parse((string) $row->updated_at);

                    if ($updatedAt->diffInSeconds($createdAt) >= 5) {
                        $renewedIds[] = $row->id;
                    }
                }

                if ($renewedIds !== []) {
                    DB::table('invoices')
                        ->whereIn('id', $renewedIds)
                        ->update(['renewed_without_payment' => true]);
                }
            }, 'id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('renewed_without_payment');
        });
    }
};
