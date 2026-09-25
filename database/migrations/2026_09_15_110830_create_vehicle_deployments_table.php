<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_deployments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->cascadeOnDelete();

            $table->foreignId('destination_location_id')
                ->constrained('locations')
                ->restrictOnDelete();

            $table->string('purpose')->nullable();

            $table->string('status')->default('planned');

            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['vehicle_id', 'status']);
            $table->index('destination_location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_deployments');
    }
};
