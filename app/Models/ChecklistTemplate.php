<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $category
 * @property bool $active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ChecklistTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_key',
        'name',
        'description',
        'category',
        'role',
        'frequency',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ChecklistItem::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ChecklistTemplateField::class)->orderBy('sequence');
    }
}
