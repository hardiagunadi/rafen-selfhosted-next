<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $licenseExpiryDate = now()->addDays(365)->toDateString();

        DB::table('users')
            ->where('subscription_method', 'license')
            ->where('subscription_status', 'trial')
            ->update([
                'subscription_status' => 'active',
                'trial_days_remaining' => 0,
            ]);

        DB::table('users')
            ->where('subscription_method', 'license')
            ->whereNull('subscription_expires_at')
            ->update([
                'subscription_expires_at' => $licenseExpiryDate,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
