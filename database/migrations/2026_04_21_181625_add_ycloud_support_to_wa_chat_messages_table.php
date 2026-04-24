<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_chat_messages', function (Blueprint $table) {
            $table->string('provider', 20)->default('local')->after('owner_id');
            $table->string('message_type', 50)->nullable()->after('message');
            $table->string('pricing_category', 50)->nullable()->after('message_type');
            $table->boolean('is_free_window_send')->default(false)->after('pricing_category');
            $table->string('delivery_status', 50)->nullable()->after('is_free_window_send');
            $table->json('pricing_metadata')->nullable()->after('delivery_status');
            $table->string('provider_message_id', 255)->nullable()->after('wa_message_id');

            $table->index(['provider', 'provider_message_id'], 'wa_chat_messages_provider_message_idx');
        });
    }

    public function down(): void
    {
        Schema::table('wa_chat_messages', function (Blueprint $table) {
            $table->dropIndex('wa_chat_messages_provider_message_idx');
            $table->dropColumn([
                'provider',
                'message_type',
                'pricing_category',
                'is_free_window_send',
                'delivery_status',
                'pricing_metadata',
                'provider_message_id',
            ]);
        });
    }
};
