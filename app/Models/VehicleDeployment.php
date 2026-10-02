<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class VehicleDeployment extends Model
{
    protected $fillable = [
        'vehicle_id',
        'destination_type',
        'destination_id',
        'purpose',
        'status',
        'dispatched_at',
        'started_at',
        'completed_at',
        'notes',
        'driver_external_user_id',
        'driver_name',
        'driver_phone',
        'journey_state',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Vehicle assigned to this deployment.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Deployment destination.
     *
     * Can be either:
     * - Shop
     * - Location
     */
    public function destination(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Determine whether this deployment is currently active.
     */
    public function isActive(): bool
    {
        return in_array(
            $this->status,
            [
                'planned',
                'dispatched',
                'in_progress',
            ],
            true
        );
    }

    /**
     * Determine whether the deployment has been completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Determine whether the deployment was cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Determine if driver is currently going outbound to destination.
     */
    public function isGoing(): bool
    {
        return $this->journey_state === 'going';
    }

    /**
     * Determine if driver is currently returning to base.
     */
    public function isGoingBack(): bool
    {
        return $this->journey_state === 'going_back';
    }

    /**
     * Determine if driver has arrived at the destination/stop.
     */
    public function isAtStop(): bool
    {
        return $this->journey_state === 'at_stop';
    }
}
