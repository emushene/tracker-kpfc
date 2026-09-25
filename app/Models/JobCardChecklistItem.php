<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $job_card_checklist_id
 * @property int $sequence
 * @property string $label
 * @property string|null $description
 * @property bool $required
 * @property bool $is_checked
 * @property Carbon|null $checked_at
 * @property string|null $checked_by_external_user_id
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class JobCardChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_card_checklist_id',
        'sequence',
        'label',
        'description',
        'required',
        'is_checked',
        'checked_at',
        'checked_by_external_user_id',
        'notes',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'required' => 'boolean',
        'is_checked' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function jobCardChecklist(): BelongsTo
    {
        return $this->belongsTo(JobCardChecklist::class);
    }
}
