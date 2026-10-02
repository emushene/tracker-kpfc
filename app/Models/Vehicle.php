<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'imei',
        'device_name',
        'plate_number',
        'device_type',
        'simcard',
        'iccid',
        'activated_at',
        'online_at',
        'platform_due_at',
        'last_position_at',
        'active',
        'location_name',
        'location_latitude',
        'location_longitude',
        'location_updated_at',
        'assigned_shop_id',
        'road_distance_meters',
        'road_duration_seconds',
        'route_calculated_at',
        'route_latitude',
        'route_longitude',
        'route_destination_type',
        'route_destination_id',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'online_at' => 'datetime',
        'platform_due_at' => 'datetime',
        'last_position_at' => 'datetime',
        'active' => 'boolean',
        'location_latitude' => 'float',
        'location_longitude' => 'float',
        'location_updated_at' => 'datetime',
        'road_distance_meters' => 'integer',
        'road_duration_seconds' => 'integer',
        'route_calculated_at' => 'datetime',
        'route_latitude' => 'float',
        'route_longitude' => 'float',
        'route_destination_id' => 'integer',
    ];

    /**
     * Resolve a vehicle from a route parameter by ID, plate number, or IMEI.
     *
     * This lets URL parameters like /vehicles/109 or /vehicles/KBZ-001Q resolve
     * to the correct vehicle record without extra controller logic.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        if (is_numeric($value)) {
            $vehicle = $this->where('id', $value)->first();
            if ($vehicle !== null) {
                return $vehicle;
            }
        }

        $normalizedPlate = str_replace([' ', '-'], '', (string) $value);

        return $this->where('plate_number', $value)
            ->orWhereRaw("REPLACE(REPLACE(plate_number, ' ', ''), '-', '') = ?", [$normalizedPlate])
            ->orWhere('imei', (string) $value)
            ->first();
    }

    /**
     * Compute the current fleet status from live telemetry instead of storing it in the database.
     *
     * Priority order:
     * 1. active deployment => deployed
     * 2. non-zero speed => moving
     * 3. ignition on => idling
     * 4. active vehicle => parked
     * 5. otherwise => idle
     */
    protected function status(): Attribute
    {
        return new Attribute(
            get: function () {
                $position = $this->positions()->latest('gps_time')->first();

                if ($this->deployments()
                    ->whereIn('status', ['planned', 'dispatched', 'in_progress'])
                    ->exists()) {
                    return 'deployed';
                }

                if ($position && ($position->speed ?? 0) > 0) {
                    return 'moving';
                }

                if ($position && ($position->acc_status ?? 0) === 1) {
                    return 'idling';
                }

                return $this->active ? 'parked' : 'idle';
            }
        );
    }

    /**
     * Return the permanent home shop assigned to this vehicle.
     * Used for home-base routing, distance calculations, and operational assignment.
     */
    public function assignedShop(): BelongsTo
    {
        return $this->belongsTo(
            Shop::class,
            'assigned_shop_id'
        );
    }

    /**
     * Return all GPS/telemetry position records for this vehicle.
     * The latest record is used to determine movement, last location, and speed.
     */
    public function positions(): HasMany
    {
        return $this->hasMany(VehiclePosition::class);
    }

    /**
     * Return vehicle alarm events generated from telematics or system rules.
     */
    public function alarms(): HasMany
    {
        return $this->hasMany(VehicleAlarm::class);
    }

    /**
     * Return vehicle event records such as state changes, ingest updates, and system notices.
     */
    public function events(): HasMany
    {
        return $this->hasMany(VehicleEvent::class);
    }

    /**
     * Return mileage or odometer-related records for this vehicle.
     */
    public function mileage(): HasMany
    {
        return $this->hasMany(VehicleMileage::class);
    }

    /**
     * Return playback history records for route reconstruction or historical review.
     */
    public function playbacks(): HasMany
    {
        return $this->hasMany(VehiclePlayback::class);
    }

    /**
     * Return temporary or active deployments assigned to this vehicle.
     * These control routing, dispatch status, and destination mission logic.
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(
            VehicleDeployment::class
        );
    }

    public function maintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }

    public function maintenanceAlerts(): HasMany
    {
        return $this->hasMany(MaintenanceAlert::class);
    }

    public function maintenanceTickets(): HasMany
    {
        return $this->hasMany(MaintenanceTicket::class);
    }

    public function maintenanceJobCards(): HasMany
    {
        return $this->hasMany(MaintenanceJobCard::class);
    }

    public function repairs(): HasMany
    {
        return $this->hasMany(VehicleRepair::class);
    }

    public function replacements(): HasMany
    {
        return $this->hasMany(VehicleReplacement::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function activeTrip(): HasOne
    {
        return $this->hasOne(Trip::class)->whereIn('status', ['in_progress', 'returning_to_base'])->latestOfMany();
    }

    public function activeDeployment(): HasOne
    {
        return $this->hasOne(VehicleDeployment::class)
            ->whereIn('status', ['planned', 'dispatched', 'in_progress'])
            ->latestOfMany();
    }

    /**
     * Resolve the active mission details, combining VehicleDeployment and Trip
     * into a single unified contract for direction, destination, driver, and routing.
     *
     * @return array<string, mixed>
     */
    public function resolveActiveMissionDetails(): array
    {
        $deployment = $this->relationLoaded('deployments')
            ? $this->deployments->first(fn ($d) => in_array($d->status, ['planned', 'dispatched', 'in_progress'], true))
            : ($this->relationLoaded('activeDeployment') ? $this->activeDeployment : $this->activeDeployment()->with('destination')->first());

        $trip = $this->relationLoaded('activeTrip')
            ? $this->activeTrip
            : $this->activeTrip()->with(['stops', 'activeReturnRequest'])->first();

        $hasActiveMission = $deployment !== null || $trip !== null;

        if (! $hasActiveMission) {
            $isAtBase = $this->assigned_shop_id !== null && (
                $this->location_name === $this->assignedShop?->name
                || ($this->road_distance_meters !== null && $this->road_distance_meters < 500)
            );

            return [
                'has_active_mission' => false,
                'mission_type' => null,
                'direction' => $isAtBase ? 'at_base' : ($this->active ? 'parked' : 'idle'),
                'direction_label' => $isAtBase && $this->assignedShop ? "At Base ({$this->assignedShop->name})" : 'Idle / No Active Mission',
                'status' => 'idle',
                'destination' => null,
                'driver' => null,
                'current_stop' => null,
                'origin_base' => $this->assignedShop ? [
                    'id' => $this->assignedShop->id,
                    'name' => $this->assignedShop->name,
                    'code' => $this->assignedShop->code,
                    'address' => $this->assignedShop->address,
                    'latitude' => (float) $this->assignedShop->latitude,
                    'longitude' => (float) $this->assignedShop->longitude,
                ] : null,
                'distance_remaining_km' => null,
                'eta_minutes' => null,
                'started_at' => null,
            ];
        }

        $missionType = $deployment ? 'deployment' : 'trip';
        $missionStatus = $deployment ? $deployment->status : $trip->status;
        $driverId = $deployment?->driver_external_user_id ?? $trip?->driver_external_user_id;
        $driverName = $deployment?->driver_name;
        $driverPhone = $deployment?->driver_phone;
        $startedAt = $deployment?->started_at ?? $deployment?->dispatched_at ?? $trip?->actual_start;

        $destinationData = null;
        $currentStopData = null;

        if ($deployment && $deployment->destination) {
            $dest = $deployment->destination;
            $destinationData = [
                'type' => $deployment->destination_type,
                'id' => $dest->id,
                'name' => $dest->name,
                'address' => $dest->address,
                'latitude' => (float) $dest->latitude,
                'longitude' => (float) $dest->longitude,
            ];
        } elseif ($trip) {
            $currentStop = $trip->stops?->first(fn ($s) => in_array($s->status, ['arrived', 'pending'], true))
                ?? $trip->stops?->last();

            if ($currentStop) {
                $currentStopData = [
                    'id' => $currentStop->id,
                    'sequence' => $currentStop->sequence,
                    'location_name' => $currentStop->location_name,
                    'address' => $currentStop->address,
                    'latitude' => (float) $currentStop->latitude,
                    'longitude' => (float) $currentStop->longitude,
                    'status' => $currentStop->status,
                ];

                $destinationData = [
                    'type' => $currentStop->shop_id ? 'shop' : 'location',
                    'id' => $currentStop->shop_id ?? $currentStop->id,
                    'name' => $currentStop->location_name,
                    'address' => $currentStop->address,
                    'latitude' => (float) $currentStop->latitude,
                    'longitude' => (float) $currentStop->longitude,
                ];
            }
        }

        $isReturning = ($deployment && $deployment->journey_state === 'going_back')
            || ($trip && $trip->status === 'returning_to_base');

        $isAtStop = ($deployment && $deployment->journey_state === 'at_stop')
            || ($trip && $trip->stops?->contains(fn ($s) => $s->status === 'arrived'));

        $direction = 'going';
        $directionLabel = 'Going to Destination';

        if ($isReturning) {
            $direction = 'going_back';
            $baseName = $this->assignedShop?->name ?? 'Home Base';
            $directionLabel = "Going Back to Base ({$baseName})";
        } elseif ($isAtStop) {
            $direction = 'at_stop';
            $destName = $destinationData['name'] ?? 'Stop';
            $directionLabel = "At Stop ({$destName})";
        } elseif ($destinationData) {
            $direction = 'going';
            $directionLabel = "Going to {$destinationData['name']}";
        }

        return [
            'has_active_mission' => true,
            'mission_type' => $missionType,
            'direction' => $direction,
            'direction_label' => $directionLabel,
            'status' => $missionStatus,
            'destination' => $destinationData,
            'driver' => [
                'external_id' => $driverId,
                'name' => $driverName,
                'phone' => $driverPhone,
            ],
            'current_stop' => $currentStopData,
            'origin_base' => $this->assignedShop ? [
                'id' => $this->assignedShop->id,
                'name' => $this->assignedShop->name,
                'code' => $this->assignedShop->code,
                'address' => $this->assignedShop->address,
                'latitude' => (float) $this->assignedShop->latitude,
                'longitude' => (float) $this->assignedShop->longitude,
            ] : null,
            'distance_remaining_km' => $this->road_distance_meters !== null ? round($this->road_distance_meters / 1000, 2) : null,
            'eta_minutes' => $this->road_duration_seconds !== null ? (int) round($this->road_duration_seconds / 60) : null,
            'started_at' => $startedAt?->toIso8601String(),
        ];
    }
}
