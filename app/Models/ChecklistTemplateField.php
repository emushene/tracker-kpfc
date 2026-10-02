<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistTemplateField extends Model
{
    protected $fillable = [
        'checklist_template_id',
        'field_key',
        'field_group',
        'label',
        'field_type',
        'required',
        'sequence',
    ];

    protected $casts = [
        'required' => 'boolean',
        'sequence' => 'integer',
    ];

    public function checklistTemplate(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ChecklistTemplateFieldOption::class)->orderBy('sequence');
    }
}
