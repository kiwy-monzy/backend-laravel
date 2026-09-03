<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GalleryImage extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'website_id', 'url', 'caption', 'disabled'];

    protected $casts = [
        'disabled' => 'boolean',
    ];
}