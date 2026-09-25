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
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->string('tool_code')->unique();
            $table->string('name');
            $table->string('category')->default('workshop'); // diagnostic, torque, workshop, specialized
            $table->string('availability_status')->default('available'); // available, assigned, under_maintenance, decommissioned
            $table->string('condition')->default('good'); // new, good, fair, poor, damaged
            $table->string('serial_number')->nullable();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['availability_status', 'condition']);
            $table->index('inventory_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tools');
    }
};
