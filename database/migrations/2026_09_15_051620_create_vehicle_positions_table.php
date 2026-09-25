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
        Schema::create('vehicle_positions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vehicle_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('course', 8, 2)->nullable();

            $table->decimal('battery', 8, 2)->nullable();

            $table->bigInteger('mileage')->nullable();
            $table->bigInteger('today_mileage')->nullable();
            $table->bigInteger('odometer')->nullable();

            $table->integer('acc_status')->nullable();
            $table->integer('charge_status')->nullable();
            $table->integer('oil_power_status')->nullable();
            $table->integer('door_status')->nullable();
            $table->integer('defence_status')->nullable();
            $table->integer('data_status')->nullable();

            $table->string('fuel')->nullable();
            $table->string('external_power')->nullable();

            $table->unsignedBigInteger('heart_time')->nullable();
            $table->unsignedBigInteger('gps_time')->nullable();
            $table->unsignedBigInteger('server_time')->nullable();
            $table->unsignedBigInteger('system_time')->nullable();

            $table->json('temperature')->nullable();

            $table->timestamps();

            $table->index(['vehicle_id', 'gps_time']);
            $table->index(['vehicle_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_positions');
    }
};
