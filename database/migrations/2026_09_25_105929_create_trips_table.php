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
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('trip_number', 64)->unique();
            $table->unsignedBigInteger('transport_request_id')->nullable()->index();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('driver_external_user_id', 100)->index();
            $table->string('status', 32)->default('planned')->index();
            $table->timestamp('planned_start')->nullable();
            $table->timestamp('actual_start')->nullable();
            $table->timestamp('planned_end')->nullable();
            $table->timestamp('actual_end')->nullable();
            $table->decimal('start_latitude', 10, 7)->nullable();
            $table->decimal('start_longitude', 10, 7)->nullable();
            $table->string('start_location_name')->nullable();
            $table->unsignedInteger('starting_mileage')->nullable();
            $table->unsignedInteger('ending_mileage')->nullable();
            $table->unsignedInteger('trip_mileage')->nullable();
            $table->json('planned_route')->nullable();
            $table->json('actual_route')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
