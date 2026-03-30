<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->string('wa_gateway_url')->nullable()->after('grace_period_days');
            $table->string('wa_gateway_token')->nullable()->after('wa_gateway_url');
            $table->boolean('wa_notify_registration')->default(false)->after('wa_gateway_token');
            $table->boolean('wa_notify_invoice')->default(false)->after('wa_notify_registration');
            $table->boolean('wa_notify_payment')->default(false)->after('wa_notify_invoice');
            $table->boolean('wa_broadcast_enabled')->default(false)->after('wa_notify_payment');
            $table->text('wa_template_registration')->nullable()->after('wa_broadcast_enabled');
            $table->text('wa_template_invoice')->nullable()->after('wa_template_registration');
            $table->text('wa_template_payment')->nullable()->after('wa_template_invoice');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'wa_gateway_url',
                'wa_gateway_token',
                'wa_notify_registration',
                'wa_notify_invoice',
                'wa_notify_payment',
                'wa_broadcast_enabled',
                'wa_template_registration',
                'wa_template_invoice',
                'wa_template_payment',
            ]);
        });
    }
};
