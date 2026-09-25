<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $inventory_part_id
 * @property string $movement_type
 * @property int $quantity
 * @property int $balance_after
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $actor_external_user_id
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class InventoryMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_part_id',
        'movement_type',
        'quantity',
        'balance_after',
        'reference_type',
        'reference_id',
        'actor_external_user_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'balance_after' => 'integer',
        'reference_id' => 'integer',
    ];

    /**
     * Associated inventory part.
     */
    public function inventoryPart(): BelongsTo
    {
        return $this->belongsTo(InventoryPart::class);
    }
}
