<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\TripStopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $trip_id
 * @property int $sequence
 * @property string $stop_type
 * @property int|null $shop_id
 * @property string|null $external_shop_id
 * @property string $location_name
 * @property string|null $address
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $contact_phone
 * @property string|null $delivery_instructions
 * @property string $status
 * @property Carbon|null $arrived_at
 * @property Carbon|null $departed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Trip $trip
 * @property-read Shop|null $shop
 */
class TripStop extends Model
{
    /** @use HasFactory<TripStopFactory> */
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'sequence',
        'stop_type',
        'shop_id',
        'external_shop_id',
        'location_name',
        'address',
        'latitude',
        'longitude',
        'contact_phone',
        'delivery_instructions',
        'status',
        'arrived_at',
        'departed_at',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'arrived_at' => 'datetime',
        'departed_at' => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Determine if the stop can be marked as arrived.
     * Enforces sequential stop execution: all prior stops must be arrived or completed.
     */
    public function canArrive(): bool
    {
        if (in_array($this->status, ['arrived', 'completed'], true)) {
            return false;
        }

        $pendingPrecedingStops = self::query()
            ->where('trip_id', $this->trip_id)
            ->where('sequence', '<', $this->sequence)
            ->whereNotIn('status', ['arrived', 'completed', 'cancelled'])
            ->exists();

        return ! $pendingPrecedingStops;
    }
}
