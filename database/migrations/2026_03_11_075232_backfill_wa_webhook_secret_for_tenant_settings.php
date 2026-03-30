<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $tenantSettings = DB::table('tenant_settings')
            ->whereNotNull('wa_gateway_url')
            ->where(function ($query) {
                $query->whereNull('wa_webhook_secret')
                    ->orWhere('wa_webhook_secret', '');
            })
            ->get(['id']);

        foreach ($tenantSettings as $setting) {
            DB::table('tenant_settings')
                ->where('id', $setting->id)
                ->update(['wa_webhook_secret' => Str::random(40)]);
        }
    }

    public function down(): void
    {
        // No-op. Existing secrets should not be removed automatically.
    }
};
