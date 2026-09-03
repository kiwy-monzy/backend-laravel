<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One dated event in the journal. Its lines must balance.
 */
class JournalEntry extends Model
{
    use HasUuids;

    protected $table = 'accounting_journal_entries';

    protected $fillable = [
        'organization_id', 'number', 'entry_date', 'memo', 'reference', 'source',
        // What caused this entry. Without these in `fillable`, mass assignment
        // drops them silently — and an entry that cannot say what it came from
        // cannot be found again, so re-posting a document would add a second
        // entry instead of replacing the first.
        'source_type', 'source_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'entry_id')->orderBy('position');
    }

    public function totalDebit(): int
    {
        return (int) $this->lines->sum('debit_minor');
    }

    public function totalCredit(): int
    {
        return (int) $this->lines->sum('credit_minor');
    }

    /** Debits equal credits — the one rule the journal enforces. */
    public function isBalanced(): bool
    {
        return $this->totalDebit() === $this->totalCredit();
    }

    /** The next entry number for an organization, JE-00001 style. */
    public static function nextNumber(?string $organizationId): string
    {
        $count = static::where('organization_id', $organizationId)->count();

        return sprintf('JE-%05d', $count + 1);
    }
}
