<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_job_cards', function (Blueprint $table) {
            $table->id();
            $table->string('job_card_number')->unique();
            $table->foreignId('maintenance_ticket_id')->nullable()->constrained('maintenance_tickets')->nullOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('mechanic_external_user_id')->nullable();
            $table->integer('mileage_at_service')->nullable();
            $table->text('reported_problem')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('work_performed')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'status'], 'mjc_vehicle_status_idx');
            $table->index('maintenance_ticket_id', 'mjc_ticket_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_job_cards');
    }
};
