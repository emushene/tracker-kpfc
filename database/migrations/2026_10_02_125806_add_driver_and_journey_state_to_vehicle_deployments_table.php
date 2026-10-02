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
        Schema::table('vehicle_deployments', function (Blueprint $table) {
            $table->string('driver_external_user_id', 100)->nullable()->index()->after('notes');
            $table->string('driver_name', 255)->nullable()->after('driver_external_user_id');
            $table->string('driver_phone', 50)->nullable()->after('driver_name');
            $table->string('journey_state', 32)->default('going')->index()->after('driver_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_deployments', function (Blueprint $table) {
            $table->dropIndex(['driver_external_user_id']);
            $table->dropIndex(['journey_state']);
            $table->dropColumn([
                'driver_external_user_id',
                'driver_name',
                'driver_phone',
                'journey_state',
            ]);
        });
    }
};
