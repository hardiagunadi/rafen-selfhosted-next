<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppp_users', function (Blueprint $table) {
            $table->foreignId('odp_id')->nullable()->after('profile_group_id')->constrained('odps')->nullOnDelete();
            $table->decimal('location_accuracy_m', 8, 2)->nullable()->after('longitude');
            $table->string('location_capture_method', 30)->nullable()->after('location_accuracy_m');
            $table->timestamp('location_captured_at')->nullable()->after('location_capture_method');
        });
    }

    public function down(): void
    {
        Schema::table('ppp_users', function (Blueprint $table) {
            $table->dropForeign(['odp_id']);
            $table->dropColumn([
                'odp_id',
                'location_accuracy_m',
                'location_capture_method',
                'location_captured_at',
            ]);
        });
    }
};
