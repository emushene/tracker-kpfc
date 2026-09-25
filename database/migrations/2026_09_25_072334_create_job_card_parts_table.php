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
        Schema::create('job_card_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_job_card_id')->constrained('maintenance_job_cards')->cascadeOnDelete();
            $table->foreignId('inventory_part_id')->constrained('inventory_parts')->restrictOnDelete();
            $table->integer('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->string('allocated_by_external_user_id')->nullable();
            $table->timestamps();

            $table->index(['maintenance_job_card_id', 'inventory_part_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_card_parts');
    }
};
