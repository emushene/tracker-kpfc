<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int|null $inventory_category_id
 * @property string $tool_code
 * @property string $name
 * @property string $category
 * @property string $availability_status
 * @property string $condition
 * @property string|null $serial_number
 * @property string|null $location
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Tool extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_category_id',
        'tool_code',
        'name',
        'category',
        'availability_status',
        'condition',
        'serial_number',
        'location',
        'notes',
    ];

    /**
     * Category relation.
     */
    public function inventoryCategory(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class);
    }

    /**
     * History of assignments.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ToolAssignment::class);
    }

    /**
     * Active assignment (if currently assigned out).
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(ToolAssignment::class)->whereNull('returned_at');
    }

    /**
     * Determine whether the tool is available.
     */
    public function isAvailable(): bool
    {
        return $this->availability_status === 'available';
    }

    /**
     * Scope for available tools.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('availability_status', 'available');
    }
}
