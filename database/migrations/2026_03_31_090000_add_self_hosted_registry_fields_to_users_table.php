<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_self_hosted_instance')->default(false)->after('is_super_admin');
            $table->string('self_hosted_license_id')->nullable()->after('subscription_method');
            $table->string('self_hosted_instance_name')->nullable()->after('self_hosted_license_id');
            $table->string('self_hosted_fingerprint', 80)->nullable()->after('self_hosted_instance_name');

            $table->index('is_self_hosted_instance');
            $table->unique('self_hosted_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['self_hosted_fingerprint']);
            $table->dropIndex(['is_self_hosted_instance']);
            $table->dropColumn([
                'is_self_hosted_instance',
                'self_hosted_license_id',
                'self_hosted_instance_name',
                'self_hosted_fingerprint',
            ]);
        });
    }
};
