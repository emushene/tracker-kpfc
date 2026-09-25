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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_part_id')->constrained('inventory_parts')->cascadeOnDelete();
            $table->string('movement_type'); // restock, job_card_usage, adjustment, return
            $table->integer('quantity'); // positive for in/return, negative for out/usage
            $table->integer('balance_after');
            $table->string('reference_type')->nullable(); // maintenance_job_card, purchase_order, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('actor_external_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['inventory_part_id', 'movement_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
