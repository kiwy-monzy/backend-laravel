<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentSection extends Model
{
    protected $fillable = ['website_id', 'section', 'locale', 'data'];

    protected $casts = [
        'data' => 'array',
    ];
}