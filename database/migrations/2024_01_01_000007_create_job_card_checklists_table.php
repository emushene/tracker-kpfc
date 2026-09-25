<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_card_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_job_card_id')->constrained('maintenance_job_cards')->cascadeOnDelete();
            $table->unsignedBigInteger('checklist_template_id')->nullable();
            $table->string('template_name');
            $table->string('template_category')->nullable();
            $table->timestamps();

            $table->index('maintenance_job_card_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_card_checklists');
    }
};
