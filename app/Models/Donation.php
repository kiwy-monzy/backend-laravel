<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Donation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'website_id', 'name', 'email', 'phone', 'amount', 'currency',
        'transaction_message', 'transaction_image', 'status',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'transaction_message' => $this->transaction_message,
            'transaction_image' => $this->transaction_image,
            'status' => $this->status,
            'created_at' => $this->created_at?->toRfc3339String(),
            'updated_at' => $this->updated_at?->toRfc3339String(),
        ];
    }
}