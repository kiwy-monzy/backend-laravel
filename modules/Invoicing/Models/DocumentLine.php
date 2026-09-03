<?php

namespace Modules\Invoicing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentLine extends Model
{
    protected $table = 'invoicing_lines';

    protected $fillable = [
        'document_id', 'item_id', 'name', 'description',
        'quantity', 'rate_minor', 'tax_percent', 'amount_minor', 'position',
    ];

    protected $casts = [
        'quantity' => 'float',
        'rate_minor' => 'integer',
        'tax_percent' => 'float',
        'amount_minor' => 'integer',
        'position' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    /** Quantity × rate, rounded once — never quantity × rate on each read. */
    public function computedAmountMinor(): int
    {
        return (int) round($this->quantity * $this->rate_minor);
    }
}
