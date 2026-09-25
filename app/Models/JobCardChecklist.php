<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $maintenance_job_card_id
 * @property int|null $checklist_template_id
 * @property string $template_name
 * @property string|null $template_category
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class JobCardChecklist extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_job_card_id',
        'checklist_template_id',
        'template_name',
        'template_category',
    ];

    protected $casts = [
        'checklist_template_id' => 'integer',
    ];

    public function maintenanceJobCard(): BelongsTo
    {
        return $this->belongsTo(MaintenanceJobCard::class);
    }

    public function jobCardChecklistItems(): HasMany
    {
        return $this->hasMany(JobCardChecklistItem::class);
    }
}
