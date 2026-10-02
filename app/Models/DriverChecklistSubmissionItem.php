<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverChecklistSubmissionItem extends Model
{
    protected $fillable = [
        'driver_checklist_submission_id',
        'checklist_item_id',
        'item_key',
        'section_title',
        'sequence',
        'label',
        'description',
        'required',
        'result',
        'notes',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'required' => 'boolean',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(DriverChecklistSubmission::class, 'driver_checklist_submission_id');
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class);
    }
}
