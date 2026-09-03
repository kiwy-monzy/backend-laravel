<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Volunteer extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'website_id', 'name', 'email', 'phone', 'skills', 'availability', 'motivation', 'status',
    ];

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'skills' => $this->skills,
            'availability' => $this->availability,
            'motivation' => $this->motivation,
            'status' => $this->status,
            'created_at' => $this->created_at?->toRfc3339String(),
            'updated_at' => $this->updated_at?->toRfc3339String(),
        ];
    }
}