<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_cache', function (Blueprint $table) {
            $table->string('latitude_bucket', 20)
                ->after('longitude');

            $table->string('longitude_bucket', 20)
                ->after('latitude_bucket');

            $table->index([
                'latitude_bucket',
                'longitude_bucket',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('location_cache', function (Blueprint $table) {
            $table->dropIndex([
                'location_cache_latitude_bucket_longitude_bucket_index',
            ]);

            $table->dropColumn([
                'latitude_bucket',
                'longitude_bucket',
            ]);
        });
    }
};
