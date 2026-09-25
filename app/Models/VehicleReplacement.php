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
 * @property string $part_name
 * @property string|null $old_part_description
 * @property string|null $new_part_description
 * @property string|null $reason
 * @property int|null $mileage_at_replacement
 * @property Carbon $replaced_at
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class VehicleReplacement extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'maintenance_job_card_id',
        'mechanic_external_user_id',
        'part_name',
        'old_part_description',
        'new_part_description',
        'reason',
        'mileage_at_replacement',
        'replaced_at',
        'notes',
    ];

    protected $casts = [
        'mileage_at_replacement' => 'integer',
        'replaced_at' => 'date',
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
