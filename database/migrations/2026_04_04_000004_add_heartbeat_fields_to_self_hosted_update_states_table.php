<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('self_hosted_update_states', function (Blueprint $table) {
            $table->timestamp('last_heartbeat_at')->nullable()->after('last_applied_at');
            $table->string('last_heartbeat_status')->nullable()->after('last_apply_status');
            $table->text('last_heartbeat_message')->nullable()->after('last_apply_message');
            $table->unsignedBigInteger('last_heartbeat_status_id')->nullable()->after('rollback_ref');
            $table->json('last_heartbeat_response')->nullable()->after('manifest_payload');
        });
    }

    public function down(): void
    {
        Schema::table('self_hosted_update_states', function (Blueprint $table) {
            $table->dropColumn([
                'last_heartbeat_at',
                'last_heartbeat_status',
                'last_heartbeat_message',
                'last_heartbeat_status_id',
                'last_heartbeat_response',
            ]);
        });
    }
};
