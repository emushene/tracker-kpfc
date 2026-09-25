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
        Schema::create('return_to_base_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->string('driver_external_user_id', 100)->index();
            $table->text('reason');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('current_location_name')->nullable();
            $table->json('undelivered_stops')->nullable();
            $table->timestamp('requested_at');
            $table->string('status', 32)->default('pending')->index();
            $table->string('decision', 32)->nullable();
            $table->string('decision_maker_external_user_id', 100)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('manager_comments')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_to_base_requests');
    }
};
