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
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->boolean('wa_notify_on_process')->default(true)->after('wa_notify_payment');
            $table->text('wa_template_on_process')->nullable()->after('wa_template_payment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn(['wa_notify_on_process', 'wa_template_on_process']);
        });
    }
};
