<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $vehicle_id
 * @property int|null $maintenance_schedule_id
 * @property string $alert_type
 * @property string $title
 * @property string $message
 * @property int|null $current_km
 * @property int|null $threshold_km
 * @property string $status
 * @property string|null $acknowledged_by_external_user_id
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MaintenanceAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'maintenance_schedule_id',
        'alert_type',
        'title',
        'message',
        'current_km',
        'threshold_km',
        'status',
        'acknowledged_by_external_user_id',
        'acknowledged_at',
        'resolved_at',
    ];

    protected $casts = [
        'current_km' => 'integer',
        'threshold_km' => 'integer',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function maintenanceSchedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class);
    }
}
