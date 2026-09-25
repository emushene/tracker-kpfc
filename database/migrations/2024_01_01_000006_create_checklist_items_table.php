<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_template_id')->constrained('checklist_templates')->cascadeOnDelete();
            $table->integer('sequence');
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('required')->default(true);
            $table->timestamps();

            $table->index(['checklist_template_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
    }
};
