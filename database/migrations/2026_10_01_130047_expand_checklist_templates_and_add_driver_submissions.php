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
        if (! Schema::hasColumn('checklist_templates', 'template_key')) {
            Schema::table('checklist_templates', function (Blueprint $table): void {
                $table->string('template_key')->nullable();
            });
        }

        if (! Schema::hasColumn('checklist_templates', 'role')) {
            Schema::table('checklist_templates', function (Blueprint $table): void {
                $table->string('role')->default('mechanic');
            });
        }

        if (! Schema::hasColumn('checklist_templates', 'frequency')) {
            Schema::table('checklist_templates', function (Blueprint $table): void {
                $table->string('frequency')->nullable();
            });
        }

        if (! Schema::hasIndex('checklist_templates', 'checklist_templates_template_key_unique')) {
            Schema::table('checklist_templates', function (Blueprint $table): void {
                $table->unique('template_key', 'checklist_templates_template_key_unique');
            });
        }

        if (! Schema::hasColumn('checklist_items', 'item_key')) {
            Schema::table('checklist_items', function (Blueprint $table): void {
                $table->string('item_key')->nullable();
            });
        }

        if (! Schema::hasColumn('checklist_items', 'section_title')) {
            Schema::table('checklist_items', function (Blueprint $table): void {
                $table->string('section_title')->nullable();
            });
        }

        if (! Schema::hasIndex('checklist_items', 'checklist_items_checklist_template_id_item_key_unique')) {
            Schema::table('checklist_items', function (Blueprint $table): void {
                $table->unique(['checklist_template_id', 'item_key']);
            });
        }

        if (! Schema::hasColumn('job_card_checklist_items', 'item_key')) {
            Schema::table('job_card_checklist_items', function (Blueprint $table): void {
                $table->string('item_key')->nullable();
            });
        }

        if (! Schema::hasColumn('job_card_checklist_items', 'section_title')) {
            Schema::table('job_card_checklist_items', function (Blueprint $table): void {
                $table->string('section_title')->nullable();
            });
        }

        if (! Schema::hasColumn('job_card_checklist_items', 'result')) {
            Schema::table('job_card_checklist_items', function (Blueprint $table): void {
                $table->string('result')->default('unchecked');
            });
        }

        if (! Schema::hasTable('checklist_template_fields')) {
            Schema::create('checklist_template_fields', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('checklist_template_id')->constrained()->cascadeOnDelete();
                $table->string('field_key');
                $table->string('field_group');
                $table->string('label');
                $table->string('field_type');
                $table->boolean('required')->default(false);
                $table->unsignedInteger('sequence');
                $table->timestamps();

                $table->unique(['checklist_template_id', 'field_key']);
            });
        }

        if (! Schema::hasIndex('checklist_template_fields', 'ctf_template_group_seq_idx')) {
            Schema::table('checklist_template_fields', function (Blueprint $table): void {
                $table->index(['checklist_template_id', 'field_group', 'sequence'], 'ctf_template_group_seq_idx');
            });
        }

        if (! Schema::hasTable('checklist_template_field_options')) {
            Schema::create('checklist_template_field_options', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('checklist_template_field_id');
                $table->string('option_value');
                $table->string('label');
                $table->unsignedInteger('sequence');
                $table->timestamps();
                $table->foreign('checklist_template_field_id', 'ctfo_field_fk')
                    ->references('id')->on('checklist_template_fields')->cascadeOnDelete();
                $table->unique(['checklist_template_field_id', 'option_value'], 'ctfo_field_option_unique');
            });
        }

        if (! Schema::hasForeignKey('checklist_template_field_options', 'ctfo_field_fk')) {
            Schema::table('checklist_template_field_options', function (Blueprint $table): void {
                $table->foreign('checklist_template_field_id', 'ctfo_field_fk')
                    ->references('id')->on('checklist_template_fields')->cascadeOnDelete();
            });
        }

        if (! Schema::hasIndex('checklist_template_field_options', 'ctfo_field_option_unique')) {
            Schema::table('checklist_template_field_options', function (Blueprint $table): void {
                $table->unique(['checklist_template_field_id', 'option_value'], 'ctfo_field_option_unique');
            });
        }

        if (! Schema::hasTable('job_card_checklist_field_values')) {
            Schema::create('job_card_checklist_field_values', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('job_card_checklist_id');
                $table->unsignedBigInteger('checklist_template_field_id')->nullable();
                $table->string('field_key');
                $table->string('field_group');
                $table->string('label');
                $table->text('value')->nullable();
                $table->timestamps();
                $table->foreign('job_card_checklist_id', 'jccfv_checklist_fk')
                    ->references('id')->on('job_card_checklists')->cascadeOnDelete();
                $table->foreign('checklist_template_field_id', 'jccfv_template_field_fk')
                    ->references('id')->on('checklist_template_fields')->nullOnDelete();
                $table->unique(['job_card_checklist_id', 'field_key'], 'jccfv_checklist_field_unique');
            });
        }

        if (! Schema::hasTable('driver_checklist_submissions')) {
            Schema::create('driver_checklist_submissions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('checklist_template_id');
                $table->unsignedBigInteger('vehicle_id');
                $table->string('driver_external_user_id');
                $table->date('submission_date');
                $table->unsignedInteger('odometer');
                $table->string('vehicle_status');
                $table->text('defects')->nullable();
                $table->text('action_taken')->nullable();
                $table->string('driver_signature')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
                $table->foreign('checklist_template_id', 'dcs_template_fk')
                    ->references('id')->on('checklist_templates')->restrictOnDelete();
                $table->foreign('vehicle_id', 'dcs_vehicle_fk')
                    ->references('id')->on('vehicles')->cascadeOnDelete();
                $table->index(['vehicle_id', 'submission_date'], 'dcs_vehicle_date_idx');
                $table->index(['driver_external_user_id', 'submission_date'], 'dcs_driver_date_idx');
            });
        }

        if (! Schema::hasTable('driver_checklist_submission_items')) {
            Schema::create('driver_checklist_submission_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('driver_checklist_submission_id');
                $table->unsignedBigInteger('checklist_item_id')->nullable();
                $table->string('item_key')->nullable();
                $table->string('section_title')->nullable();
                $table->unsignedInteger('sequence');
                $table->string('label');
                $table->text('description')->nullable();
                $table->boolean('required')->default(true);
                $table->string('result')->default('pending');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->foreign('driver_checklist_submission_id', 'dcsi_submission_fk')
                    ->references('id')->on('driver_checklist_submissions')->cascadeOnDelete();
                $table->foreign('checklist_item_id', 'dcsi_item_fk')
                    ->references('id')->on('checklist_items')->nullOnDelete();
                $table->index('driver_checklist_submission_id', 'dcsi_submission_idx');
            });
        }

        if (! Schema::hasTable('driver_checklist_submission_field_values')) {
            Schema::create('driver_checklist_submission_field_values', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('driver_checklist_submission_id');
                $table->unsignedBigInteger('checklist_template_field_id')->nullable();
                $table->string('field_key');
                $table->string('field_group');
                $table->string('label');
                $table->text('value')->nullable();
                $table->timestamps();
                $table->foreign('driver_checklist_submission_id', 'dcsfv_submission_fk')
                    ->references('id')->on('driver_checklist_submissions')->cascadeOnDelete();
                $table->foreign('checklist_template_field_id', 'dcsfv_template_field_fk')
                    ->references('id')->on('checklist_template_fields')->nullOnDelete();
                $table->unique(['driver_checklist_submission_id', 'field_key'], 'dcsfv_submission_field_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_checklist_submission_field_values');
        Schema::dropIfExists('driver_checklist_submission_items');
        Schema::dropIfExists('driver_checklist_submissions');
        Schema::dropIfExists('job_card_checklist_field_values');
        Schema::dropIfExists('checklist_template_field_options');
        Schema::dropIfExists('checklist_template_fields');

        Schema::table('job_card_checklist_items', function (Blueprint $table): void {
            $table->dropColumn(['item_key', 'section_title', 'result']);
        });

        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->dropUnique(['checklist_template_id', 'item_key']);
            $table->dropColumn(['item_key', 'section_title']);
        });

        Schema::table('checklist_templates', function (Blueprint $table): void {
            $table->dropUnique(['template_key']);
            $table->dropColumn(['template_key', 'role', 'frequency']);
        });
    }
};
