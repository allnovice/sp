<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyColumn extends Model
{
    protected $fillable = [
        'key',
        'label',
        'type',
        'is_visible',
        'is_required',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'is_required' => 'boolean',
            'position' => 'integer',
        ];
    }
}
