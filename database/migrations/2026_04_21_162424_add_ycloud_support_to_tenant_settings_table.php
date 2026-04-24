<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('tenant_settings', 'wa_provider')) {
                $table->string('wa_provider', 20)->default('local')->after('wa_gateway_key');
            }

            if (! Schema::hasColumn('tenant_settings', 'wa_cost_strategy')) {
                $table->string('wa_cost_strategy', 50)->default('optimize_free_window')->after('wa_provider');
            }

            if (! Schema::hasColumn('tenant_settings', 'ycloud_enabled')) {
                $table->boolean('ycloud_enabled')->default(false)->after('wa_cost_strategy');
            }

            if (! Schema::hasColumn('tenant_settings', 'ycloud_api_key')) {
                $table->text('ycloud_api_key')->nullable()->after('ycloud_enabled');
            }

            if (! Schema::hasColumn('tenant_settings', 'ycloud_webhook_secret')) {
                $table->string('ycloud_webhook_secret', 255)->nullable()->after('ycloud_api_key');
            }

            if (! Schema::hasColumn('tenant_settings', 'ycloud_waba_id')) {
                $table->string('ycloud_waba_id', 100)->nullable()->after('ycloud_webhook_secret');
            }

            if (! Schema::hasColumn('tenant_settings', 'ycloud_phone_number_id')) {
                $table->string('ycloud_phone_number_id', 100)->nullable()->after('ycloud_waba_id');
            }

            if (! Schema::hasColumn('tenant_settings', 'ycloud_business_number')) {
                $table->string('ycloud_business_number', 30)->nullable()->after('ycloud_phone_number_id');
            }

            if (! Schema::hasColumn('tenant_settings', 'ycloud_allow_group_fallback_local')) {
                $table->boolean('ycloud_allow_group_fallback_local')->default(true)->after('ycloud_business_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('tenant_settings', 'wa_provider') ? 'wa_provider' : null,
                Schema::hasColumn('tenant_settings', 'wa_cost_strategy') ? 'wa_cost_strategy' : null,
                Schema::hasColumn('tenant_settings', 'ycloud_enabled') ? 'ycloud_enabled' : null,
                Schema::hasColumn('tenant_settings', 'ycloud_api_key') ? 'ycloud_api_key' : null,
                Schema::hasColumn('tenant_settings', 'ycloud_webhook_secret') ? 'ycloud_webhook_secret' : null,
                Schema::hasColumn('tenant_settings', 'ycloud_waba_id') ? 'ycloud_waba_id' : null,
                Schema::hasColumn('tenant_settings', 'ycloud_phone_number_id') ? 'ycloud_phone_number_id' : null,
                Schema::hasColumn('tenant_settings', 'ycloud_business_number') ? 'ycloud_business_number' : null,
                Schema::hasColumn('tenant_settings', 'ycloud_allow_group_fallback_local') ? 'ycloud_allow_group_fallback_local' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
