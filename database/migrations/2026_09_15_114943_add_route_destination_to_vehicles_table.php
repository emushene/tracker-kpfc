<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('route_destination_type')
                ->nullable()
                ->after('route_longitude');

            $table->unsignedBigInteger('route_destination_id')
                ->nullable()
                ->after('route_destination_type');

            $table->index([
                'route_destination_type',
                'route_destination_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex([
                'route_destination_type',
                'route_destination_id',
            ]);

            $table->dropColumn([
                'route_destination_type',
                'route_destination_id',
            ]);
        });
    }
};
