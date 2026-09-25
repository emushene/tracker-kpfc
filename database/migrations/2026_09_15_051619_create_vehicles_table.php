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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();

            $table->string('imei', 20)->unique();
            $table->string('device_name')->nullable();
            $table->string('plate_number')->nullable()->index();
            $table->string('device_type')->nullable();
            $table->string('simcard')->nullable();
            $table->string('iccid')->nullable();

            $table->timestamp('activated_at')->nullable();
            $table->timestamp('online_at')->nullable();
            $table->timestamp('platform_due_at')->nullable();

            $table->boolean('active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
