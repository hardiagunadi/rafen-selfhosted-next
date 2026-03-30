<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_tickets', function (Blueprint $table) {
            $table->string('customer_type', 20)->nullable()->after('conversation_id'); // ppp / hotspot
            $table->unsignedBigInteger('customer_id')->nullable()->after('customer_type');
        });
    }

    public function down(): void
    {
        Schema::table('wa_tickets', function (Blueprint $table) {
            $table->dropColumn(['customer_type', 'customer_id']);
        });
    }
};
