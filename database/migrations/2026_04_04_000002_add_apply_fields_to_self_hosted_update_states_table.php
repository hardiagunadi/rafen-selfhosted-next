<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('self_hosted_update_states', function (Blueprint $table) {
            $table->string('current_ref')->nullable()->after('current_commit');
            $table->string('latest_ref')->nullable()->after('latest_commit');
            $table->timestamp('last_applied_at')->nullable()->after('last_checked_at');
            $table->string('last_apply_status')->nullable()->after('last_check_status');
            $table->text('last_apply_message')->nullable()->after('last_check_message');
            $table->string('rollback_ref')->nullable()->after('last_apply_message');
        });
    }

    public function down(): void
    {
        Schema::table('self_hosted_update_states', function (Blueprint $table) {
            $table->dropColumn([
                'current_ref',
                'latest_ref',
                'last_applied_at',
                'last_apply_status',
                'last_apply_message',
                'rollback_ref',
            ]);
        });
    }
};
