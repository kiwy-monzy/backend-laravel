<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'accounting_journal_lines';

    protected $fillable = [
        'entry_id', 'account_id', 'debit_minor', 'credit_minor', 'memo', 'position',
    ];

    protected $casts = [
        'debit_minor' => 'integer',
        'credit_minor' => 'integer',
        'position' => 'integer',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
