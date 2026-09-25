<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedInteger('road_distance_meters')
                ->nullable()
                ->after('assigned_shop_id');

            $table->unsignedInteger('road_duration_seconds')
                ->nullable()
                ->after('road_distance_meters');

            $table->timestamp('route_calculated_at')
                ->nullable()
                ->after('road_duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'road_distance_meters',
                'road_duration_seconds',
                'route_calculated_at',
            ]);
        });
    }
};
