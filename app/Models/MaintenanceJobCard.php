<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $job_card_number
 * @property int|null $maintenance_ticket_id
 * @property int $vehicle_id
 * @property string|null $mechanic_external_user_id
 * @property int|null $mileage_at_service
 * @property string|null $reported_problem
 * @property string|null $diagnosis
 * @property string|null $work_performed
 * @property string|null $notes
 * @property string $status
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MaintenanceJobCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_card_number',
        'maintenance_ticket_id',
        'vehicle_id',
        'mechanic_external_user_id',
        'mileage_at_service',
        'reported_problem',
        'diagnosis',
        'work_performed',
        'notes',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'mileage_at_service' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function maintenanceTicket(): BelongsTo
    {
        return $this->belongsTo(MaintenanceTicket::class);
    }

    public function jobCardChecklists(): HasMany
    {
        return $this->hasMany(JobCardChecklist::class);
    }

    public function vehicleRepairs(): HasMany
    {
        return $this->hasMany(VehicleRepair::class);
    }

    public function vehicleReplacements(): HasMany
    {
        return $this->hasMany(VehicleReplacement::class);
    }

    public function jobCardParts(): HasMany
    {
        return $this->hasMany(JobCardPart::class);
    }

    public function toolAssignments(): HasMany
    {
        return $this->hasMany(ToolAssignment::class);
    }
}
