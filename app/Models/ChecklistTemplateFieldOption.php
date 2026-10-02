<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistTemplateFieldOption extends Model
{
    protected $fillable = [
        'checklist_template_field_id',
        'option_value',
        'label',
        'sequence',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function checklistTemplateField(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplateField::class);
    }
}
