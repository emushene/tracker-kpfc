<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $ticket_number
 * @property int $vehicle_id
 * @property string $ticket_type
 * @property string $status
 * @property string $title
 * @property string|null $description
 * @property string|null $reported_by_external_user_id
 * @property string|null $assigned_to_external_user_id
 * @property string $priority
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MaintenanceTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'vehicle_id',
        'ticket_type',
        'status',
        'title',
        'description',
        'reported_by_external_user_id',
        'assigned_to_external_user_id',
        'priority',
        'opened_at',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function maintenanceJobCards(): HasMany
    {
        return $this->hasMany(MaintenanceJobCard::class);
    }
}
