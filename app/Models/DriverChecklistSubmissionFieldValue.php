<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverChecklistSubmissionFieldValue extends Model
{
    protected $fillable = [
        'driver_checklist_submission_id',
        'checklist_template_field_id',
        'field_key',
        'field_group',
        'label',
        'value',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(DriverChecklistSubmission::class, 'driver_checklist_submission_id');
    }

    public function checklistTemplateField(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplateField::class);
    }
}
