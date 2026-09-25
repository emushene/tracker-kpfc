<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $inventory_category_id
 * @property string $part_number
 * @property string $name
 * @property string $category
 * @property string|null $description
 * @property int $quantity
 * @property int $minimum_quantity
 * @property string $unit_of_measure
 * @property string|null $location
 * @property string|null $unit_cost_reference
 * @property bool $active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class InventoryPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_category_id',
        'part_number',
        'name',
        'category',
        'description',
        'quantity',
        'minimum_quantity',
        'unit_of_measure',
        'location',
        'unit_cost_reference',
        'active',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'minimum_quantity' => 'integer',
        'active' => 'boolean',
    ];

    /**
     * Category relation.
     */
    public function inventoryCategory(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class);
    }

    /**
     * Stock movements for this part.
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Usages on job cards.
     */
    public function jobCardParts(): HasMany
    {
        return $this->hasMany(JobCardPart::class);
    }

    /**
     * Determine whether stock is below the minimum threshold.
     */
    public function isLowStock(): bool
    {
        return $this->quantity < $this->minimum_quantity;
    }

    /**
     * Scope query to only parts below minimum stock quantity.
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('quantity', '<', 'minimum_quantity');
    }
}
