<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id', 'website_id', 'organization_id', 'collection_id', 'path',
        'filename', 'mime', 'size', 'url', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function collection(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StorageCollection::class, 'collection_id');
    }

    public function organization(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function humanSize(): string
    {
        return $this->size > 1048576
            ? number_format($this->size / 1048576, 1) . ' MB'
            : number_format(max(1, $this->size / 1024)) . ' KB';
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'mime' => $this->mime,
            'size' => $this->size,
            'modified' => $this->created_at?->toRfc3339String(),
            'url' => $this->url,
        ];
    }
}