<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->decimal('platform_fee_percent', 5, 2)->default(0)->after('is_active')
                ->comment('Total fee % charged to tenant (gateway cost + markup). Default 0.');
            $table->string('fee_description')->nullable()->after('platform_fee_percent')
                ->comment('Human-readable fee explanation shown to tenant during setup');
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->dropColumn(['platform_fee_percent', 'fee_description']);
        });
    }
};
