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
        Schema::create('vehicle_events', function (Blueprint $table) {
    $table->id();

    $table->foreignId('vehicle_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->string('event_type')->index();

    $table->decimal('latitude', 10, 7)->nullable();
    $table->decimal('longitude', 10, 7)->nullable();
    $table->decimal('speed', 8, 2)->nullable();

    $table->unsignedBigInteger('event_time')->nullable();

    $table->json('metadata')->nullable();

    $table->timestamps();

    $table->index(['vehicle_id', 'event_time']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_events');
    }
};
