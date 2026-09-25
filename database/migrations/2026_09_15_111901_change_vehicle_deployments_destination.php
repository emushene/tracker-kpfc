<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_deployments', function (Blueprint $table) {
            $table->dropForeign([
                'destination_location_id',
            ]);

            $table->dropIndex([
                'destination_location_id',
            ]);

            $table->dropColumn([
                'destination_location_id',
            ]);

            $table->string('destination_type')
                ->after('vehicle_id');

            $table->unsignedBigInteger('destination_id')
                ->after('destination_type');

            $table->index([
                'destination_type',
                'destination_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_deployments', function (Blueprint $table) {
            $table->dropIndex([
                'destination_type',
                'destination_id',
            ]);

            $table->dropColumn([
                'destination_type',
                'destination_id',
            ]);

            $table->foreignId('destination_location_id')
                ->after('vehicle_id')
                ->constrained('locations')
                ->restrictOnDelete();

            $table->index('destination_location_id');
        });
    }
};
