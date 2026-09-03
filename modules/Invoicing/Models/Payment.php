<?php

namespace Modules\Invoicing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const METHODS = [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank transfer',
        'mobile_money' => 'Mobile money',
        'cheque' => 'Cheque',
        'card' => 'Card',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'invoicing_payments';

    protected $fillable = [
        'id', 'organization_id', 'document_id', 'customer_id',
        'amount_minor', 'paid_on', 'method', 'reference', 'notes',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'paid_on' => 'date',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }
}
