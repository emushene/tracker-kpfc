<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $type
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class InventoryCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
    ];

    /**
     * Parts under this category.
     */
    public function parts(): HasMany
    {
        return $this->hasMany(InventoryPart::class);
    }

    /**
     * Tools under this category.
     */
    public function tools(): HasMany
    {
        return $this->hasMany(Tool::class);
    }
}
