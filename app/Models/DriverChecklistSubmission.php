<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverChecklistSubmission extends Model
{
    protected $fillable = [
        'checklist_template_id',
        'vehicle_id',
        'driver_external_user_id',
        'submission_date',
        'odometer',
        'vehicle_status',
        'defects',
        'action_taken',
        'driver_signature',
        'submitted_at',
    ];

    protected $casts = [
        'submission_date' => 'date',
        'odometer' => 'integer',
        'submitted_at' => 'datetime',
    ];

    public function checklistTemplate(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DriverChecklistSubmissionItem::class)->orderBy('sequence');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(DriverChecklistSubmissionFieldValue::class);
    }
}
