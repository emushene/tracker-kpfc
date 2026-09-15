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
        Schema::create('vehicle_mileage', function (Blueprint $table) {
    $table->id();

    $table->foreignId('vehicle_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->date('date');

    $table->unsignedBigInteger('mileage')->nullable();
    $table->unsignedBigInteger('odometer')->nullable();

    $table->timestamps();

    $table->unique(['vehicle_id', 'date']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_mileages');
    }
};
