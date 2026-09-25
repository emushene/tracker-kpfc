<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $vehicle_id
 * @property int|null $maintenance_job_card_id
 * @property string|null $mechanic_external_user_id
 * @property string $problem
 * @property string|null $diagnosis
 * @property string $repair_performed
 * @property int|null $mileage_at_repair
 * @property Carbon $repaired_at
 * @property string|null $cost_reference
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class VehicleRepair extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'maintenance_job_card_id',
        'mechanic_external_user_id',
        'problem',
        'diagnosis',
        'repair_performed',
        'mileage_at_repair',
        'repaired_at',
        'cost_reference',
        'notes',
    ];

    protected $casts = [
        'mileage_at_repair' => 'integer',
        'repaired_at' => 'date',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function maintenanceJobCard(): BelongsTo
    {
        return $this->belongsTo(MaintenanceJobCard::class);
    }
}
