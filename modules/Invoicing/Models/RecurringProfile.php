<?php

namespace Modules\Invoicing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Crm\Models\Customer;

/**
 * A standing instruction to raise the same invoice on a cycle.
 *
 * The profile holds the terms; issuing one writes an ordinary Document, so a
 * recurring invoice is indistinguishable from a hand-raised one everywhere
 * downstream.
 */
class RecurringProfile extends Model
{
    use HasUuids;

    public const INTERVALS = [
        'weekly' => 'Weekly',
        'fortnightly' => 'Fortnightly',
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
        'yearly' => 'Yearly',
    ];

    public const STATUSES = [
        'active' => 'Active',
        'paused' => 'Paused',
        'ended' => 'Ended',
    ];

    protected $table = 'invoicing_recurring';

    protected $fillable = [
        'organization_id', 'customer_id', 'title', 'interval',
        'next_run_on', 'ends_on', 'amount_minor', 'currency',
        'status', 'issued_count', 'notes',
    ];

    protected $casts = [
        'next_run_on' => 'date',
        'ends_on' => 'date',
        'amount_minor' => 'integer',
        'issued_count' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function intervalLabel(): string
    {
        return self::INTERVALS[$this->interval] ?? $this->interval;
    }

    /** Where `next_run_on` lands after one cycle. */
    public function advance(): \Illuminate\Support\Carbon
    {
        $from = $this->next_run_on ?? now();

        return match ($this->interval) {
            'weekly' => $from->copy()->addWeek(),
            'fortnightly' => $from->copy()->addWeeks(2),
            'quarterly' => $from->copy()->addMonths(3),
            'yearly' => $from->copy()->addYear(),
            default => $from->copy()->addMonth(),
        };
    }

    /** Due today (or overdue), still running, and not past its end date. */
    public function isDue(): bool
    {
        return $this->status === 'active'
            && $this->next_run_on !== null
            && ! $this->next_run_on->isFuture()
            && ($this->ends_on === null || ! $this->ends_on->isPast());
    }
}
