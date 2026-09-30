<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('maintenance_job_card_id')->nullable()->constrained('maintenance_job_cards')->nullOnDelete();
            $table->string('mechanic_external_user_id')->nullable();
            $table->string('problem');
            $table->text('diagnosis')->nullable();
            $table->text('repair_performed');
            $table->integer('mileage_at_repair')->nullable();
            $table->date('repaired_at');
            $table->string('cost_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('vehicle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_repairs');
    }
};
