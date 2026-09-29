<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_card_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_card_checklist_id')->constrained('job_card_checklists')->cascadeOnDelete();
            $table->integer('sequence');
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('required')->default(true);
            $table->boolean('is_checked')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->string('checked_by_external_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('job_card_checklist_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_card_checklist_items');
    }
};
