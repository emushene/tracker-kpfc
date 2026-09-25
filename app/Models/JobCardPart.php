<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $maintenance_job_card_id
 * @property int $inventory_part_id
 * @property int $quantity
 * @property string|null $notes
 * @property string|null $allocated_by_external_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class JobCardPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_job_card_id',
        'inventory_part_id',
        'quantity',
        'notes',
        'allocated_by_external_user_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Associated maintenance job card.
     */
    public function maintenanceJobCard(): BelongsTo
    {
        return $this->belongsTo(MaintenanceJobCard::class);
    }

    /**
     * Associated inventory part consumed.
     */
    public function inventoryPart(): BelongsTo
    {
        return $this->belongsTo(InventoryPart::class);
    }
}
