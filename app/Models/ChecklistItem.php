<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $checklist_template_id
 * @property int $sequence
 * @property string $label
 * @property string|null $description
 * @property bool $required
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'checklist_template_id',
        'sequence',
        'label',
        'description',
        'required',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'required' => 'boolean',
    ];

    public function checklistTemplate(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class);
    }
}
