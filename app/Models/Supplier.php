<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    // Important: false means id is not auto-incrementing, because we just use the Admin DB IDs
    public $incrementing = false;

    protected $keyType = 'integer';

    protected $fillable = [
        'id',
        'name',
        'active',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'id' => 'integer',
        'active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];
}
