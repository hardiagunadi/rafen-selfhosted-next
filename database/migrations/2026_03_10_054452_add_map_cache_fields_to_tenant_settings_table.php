<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->boolean('map_cache_enabled')->default(false)->after('xendit_sandbox');
            $table->decimal('map_cache_center_lat', 10, 7)->nullable()->after('map_cache_enabled');
            $table->decimal('map_cache_center_lng', 10, 7)->nullable()->after('map_cache_center_lat');
            $table->decimal('map_cache_radius_km', 5, 2)->default(3)->after('map_cache_center_lng');
            $table->unsignedTinyInteger('map_cache_min_zoom')->default(14)->after('map_cache_radius_km');
            $table->unsignedTinyInteger('map_cache_max_zoom')->default(17)->after('map_cache_min_zoom');
            $table->unsignedInteger('map_cache_version')->default(1)->after('map_cache_max_zoom');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'map_cache_enabled',
                'map_cache_center_lat',
                'map_cache_center_lng',
                'map_cache_radius_km',
                'map_cache_min_zoom',
                'map_cache_max_zoom',
                'map_cache_version',
            ]);
        });
    }
};
