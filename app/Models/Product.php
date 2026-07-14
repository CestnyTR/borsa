<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'has_factor',
    ];

    protected function casts(): array
    {
        return [
            'has_factor' => 'float',
        ];
    }
}

