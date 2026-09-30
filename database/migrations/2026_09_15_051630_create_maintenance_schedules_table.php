<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('schedule_type');
            $table->string('service_name');
            $table->integer('interval_km')->nullable();
            $table->integer('interval_days')->nullable();
            $table->integer('last_service_km')->nullable();
            $table->timestamp('last_service_at')->nullable();
            $table->integer('next_service_km')->nullable();
            $table->timestamp('next_service_at')->nullable();
            $table->integer('alert_threshold_km')->nullable();
            $table->integer('alert_threshold_days')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['vehicle_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
    }
};
