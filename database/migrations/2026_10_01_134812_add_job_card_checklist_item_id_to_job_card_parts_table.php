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
        Schema::table('job_card_parts', function (Blueprint $table) {
            $table->foreignId('job_card_checklist_item_id')
                ->nullable()
                ->after('inventory_part_id')
                ->constrained('job_card_checklist_items')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_card_parts', function (Blueprint $table) {
            $table->dropForeign(['job_card_checklist_item_id']);
            $table->dropColumn('job_card_checklist_item_id');
        });
    }
};
