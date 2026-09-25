<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $trip_number
 * @property int|null $transport_request_id
 * @property int $vehicle_id
 * @property string $driver_external_user_id
 * @property string $status
 * @property Carbon|null $planned_start
 * @property Carbon|null $actual_start
 * @property Carbon|null $planned_end
 * @property Carbon|null $actual_end
 * @property float|null $start_latitude
 * @property float|null $start_longitude
 * @property string|null $start_location_name
 * @property int|null $starting_mileage
 * @property int|null $ending_mileage
 * @property int|null $trip_mileage
 * @property array|null $planned_route
 * @property array|null $actual_route
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Vehicle $vehicle
 * @property-read Collection<int, TripStop> $stops
 * @property-read Collection<int, ReturnToBaseRequest> $returnToBaseRequests
 */
class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use HasFactory;

    protected $fillable = [
        'trip_number',
        'transport_request_id',
        'vehicle_id',
        'driver_external_user_id',
        'status',
        'planned_start',
        'actual_start',
        'planned_end',
        'actual_end',
        'start_latitude',
        'start_longitude',
        'start_location_name',
        'starting_mileage',
        'ending_mileage',
        'trip_mileage',
        'planned_route',
        'actual_route',
    ];

    protected $casts = [
        'planned_start' => 'datetime',
        'actual_start' => 'datetime',
        'planned_end' => 'datetime',
        'actual_end' => 'datetime',
        'start_latitude' => 'float',
        'start_longitude' => 'float',
        'starting_mileage' => 'integer',
        'ending_mileage' => 'integer',
        'trip_mileage' => 'integer',
        'planned_route' => 'array',
        'actual_route' => 'array',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(TripStop::class)->orderBy('sequence');
    }

    public function returnToBaseRequests(): HasMany
    {
        return $this->hasMany(ReturnToBaseRequest::class);
    }

    public function activeReturnRequest(): HasOne
    {
        return $this->hasOne(ReturnToBaseRequest::class)->latestOfMany();
    }

    public function scopeForDriver(Builder $query, string $driverId): Builder
    {
        return $query->where('driver_external_user_id', $driverId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['in_progress', 'returning_to_base']);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', 'planned')->orderBy('planned_start');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['in_progress', 'returning_to_base'], true);
    }
}
