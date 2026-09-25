<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $vehicle_id
 * @property string $schedule_type
 * @property string $service_name
 * @property int|null $interval_km
 * @property int|null $interval_days
 * @property int|null $last_service_km
 * @property Carbon|null $last_service_at
 * @property int|null $next_service_km
 * @property Carbon|null $next_service_at
 * @property int|null $alert_threshold_km
 * @property int|null $alert_threshold_days
 * @property string|null $notes
 * @property bool $active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MaintenanceSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'schedule_type',
        'service_name',
        'interval_km',
        'interval_days',
        'last_service_km',
        'last_service_at',
        'next_service_km',
        'next_service_at',
        'alert_threshold_km',
        'alert_threshold_days',
        'notes',
        'active',
    ];

    protected $casts = [
        'interval_km' => 'integer',
        'interval_days' => 'integer',
        'last_service_km' => 'integer',
        'last_service_at' => 'datetime',
        'next_service_km' => 'integer',
        'next_service_at' => 'datetime',
        'alert_threshold_km' => 'integer',
        'alert_threshold_days' => 'integer',
        'active' => 'boolean',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function maintenanceAlerts(): HasMany
    {
        return $this->hasMany(MaintenanceAlert::class);
    }
}
