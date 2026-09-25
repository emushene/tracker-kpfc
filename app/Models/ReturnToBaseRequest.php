<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\ReturnToBaseRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $trip_id
 * @property string $driver_external_user_id
 * @property string $reason
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $current_location_name
 * @property array|null $undelivered_stops
 * @property Carbon $requested_at
 * @property string $status
 * @property string|null $decision
 * @property string|null $decision_maker_external_user_id
 * @property Carbon|null $decided_at
 * @property string|null $manager_comments
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Trip $trip
 */
class ReturnToBaseRequest extends Model
{
    /** @use HasFactory<ReturnToBaseRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'driver_external_user_id',
        'reason',
        'latitude',
        'longitude',
        'current_location_name',
        'undelivered_stops',
        'requested_at',
        'status',
        'decision',
        'decision_maker_external_user_id',
        'decided_at',
        'manager_comments',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'undelivered_stops' => 'array',
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
