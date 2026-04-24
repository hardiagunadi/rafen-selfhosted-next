<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->string('provider', 20)->default('local')->after('owner_id');
            $table->string('provider_customer_key', 191)->nullable()->after('session_id');
            $table->timestamp('last_inbound_at')->nullable()->after('last_message_at');
            $table->timestamp('service_window_expires_at')->nullable()->after('last_inbound_at');
        });

        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->dropUnique('wa_conversations_owner_id_contact_phone_unique');
            $table->unique(['owner_id', 'provider', 'contact_phone'], 'wa_conversations_owner_provider_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->dropUnique('wa_conversations_owner_provider_phone_unique');
            $table->unique(['owner_id', 'contact_phone']);
            $table->dropColumn([
                'provider',
                'provider_customer_key',
                'last_inbound_at',
                'service_window_expires_at',
            ]);
        });
    }
};
