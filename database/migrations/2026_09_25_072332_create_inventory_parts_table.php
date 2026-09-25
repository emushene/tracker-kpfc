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
        Schema::create('inventory_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->string('part_number')->unique();
            $table->string('name');
            $table->string('category')->default('mechanical'); // mechanical, cosmetic_body
            $table->text('description')->nullable();
            $table->integer('quantity')->default(0);
            $table->integer('minimum_quantity')->default(0);
            $table->string('unit_of_measure')->default('piece');
            $table->string('location')->nullable();
            $table->string('unit_cost_reference')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['category', 'active']);
            $table->index('inventory_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_parts');
    }
};
