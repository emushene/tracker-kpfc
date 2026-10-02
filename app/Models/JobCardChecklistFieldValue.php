<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobCardChecklistFieldValue extends Model
{
    protected $fillable = [
        'job_card_checklist_id',
        'checklist_template_field_id',
        'field_key',
        'field_group',
        'label',
        'value',
    ];

    public function jobCardChecklist(): BelongsTo
    {
        return $this->belongsTo(JobCardChecklist::class);
    }

    public function checklistTemplateField(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplateField::class);
    }
}
