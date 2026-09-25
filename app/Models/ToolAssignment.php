<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tool_id
 * @property string $mechanic_external_user_id
 * @property int|null $maintenance_job_card_id
 * @property Carbon $assigned_at
 * @property Carbon|null $expected_return_at
 * @property Carbon|null $returned_at
 * @property string|null $assigned_by_external_user_id
 * @property string|null $received_by_external_user_id
 * @property string|null $condition_on_return
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ToolAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tool_id',
        'mechanic_external_user_id',
        'maintenance_job_card_id',
        'assigned_at',
        'expected_return_at',
        'returned_at',
        'assigned_by_external_user_id',
        'received_by_external_user_id',
        'condition_on_return',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'expected_return_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    /**
     * Assigned tool.
     */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    /**
     * Associated maintenance job card.
     */
    public function maintenanceJobCard(): BelongsTo
    {
        return $this->belongsTo(MaintenanceJobCard::class);
    }

    /**
     * Determine whether the tool has been returned.
     */
    public function isReturned(): bool
    {
        return $this->returned_at !== null;
    }

    /**
     * Scope for ongoing (unreturned) assignments.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('returned_at');
    }
}
