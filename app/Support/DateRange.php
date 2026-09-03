<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The period a report or an overview is looking at.
 *
 * **One vocabulary for every module.** Each module otherwise grows its own
 * "this month" — and they disagree about whether the month starts on the 1st
 * or thirty days ago, which is how two pages show two different revenue
 * figures for the same business.
 *
 * `all` returns a null start so a query can skip the `where` entirely rather
 * than reaching back to the epoch.
 */
final class DateRange
{
    public const PRESETS = [
        'today' => 'Today',
        'week' => 'This week',
        'month' => 'This month',
        'last30' => 'Last 30 days',
        'year' => 'This year',
        'all' => 'All time',
    ];

    public function __construct(
        public readonly string $key,
        public readonly ?Carbon $start,
        public readonly ?Carbon $end,
    ) {
    }

    public static function make(?string $key): self
    {
        $key = array_key_exists((string) $key, self::PRESETS) ? $key : 'month';
        $now = now();

        [$start, $end] = match ($key) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last30' => [$now->copy()->subDays(30)->startOfDay(), $now->copy()->endOfDay()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [null, null],
        };

        return new self($key, $start, $end);
    }

    public function label(): string
    {
        return self::PRESETS[$this->key];
    }

    /** How the period reads on the page — "1 Aug 2026 – 13 Aug 2026". */
    public function caption(): string
    {
        if (! $this->start) {
            return __('Everything on record');
        }

        return $this->start->format('j M Y') . ' – ' . $this->end->format('j M Y');
    }

    /**
     * Constrain a query to the period.
     *
     * A no-op for `all`, which is why callers can apply it unconditionally.
     */
    public function apply(\Illuminate\Database\Eloquent\Builder $query, string $column): \Illuminate\Database\Eloquent\Builder
    {
        if (! $this->start) {
            return $query;
        }

        return $query->whereBetween($column, [$this->start, $this->end]);
    }
}
