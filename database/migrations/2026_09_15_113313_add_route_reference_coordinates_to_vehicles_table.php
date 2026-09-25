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
            $table->decimal('route_latitude', 10, 7)
                ->nullable()
                ->after('route_calculated_at');

            $table->decimal('route_longitude', 10, 7)
                ->nullable()
                ->after('route_latitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'route_latitude',
                'route_longitude',
            ]);
        });
    }
};
