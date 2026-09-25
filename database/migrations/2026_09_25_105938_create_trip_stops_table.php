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
        Schema::create('trip_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('stop_type', 32)->default('delivery');
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->string('external_shop_id', 100)->nullable();
            $table->string('location_name');
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('delivery_instructions')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->timestamps();

            $table->unique(['trip_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_stops');
    }
};
