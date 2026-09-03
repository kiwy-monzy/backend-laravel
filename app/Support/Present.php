<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * How a value looks in a list cell.
 *
 * Small, but it is the difference between a table of raw enum keys and one a
 * person can read: `partially_paid` becomes "Partially paid", a boolean
 * becomes a badge, a date stops printing a time nobody set.
 */
final class Present
{
    /**
     * @param  array<int,Field>  $fields
     * @param  bool  $html  allow badge markup; the first column is a link, so it stays plain
     */
    public static function cell(Model $record, string $attribute, array $fields, bool $html = false): string
    {
        $value = data_get($record, $attribute);
        $field = collect($fields)->first(fn (Field $f) => $f->name === $attribute);

        if ($value === null || $value === '') {
            return $html ? '<span class="dim">—</span>' : '—';
        }

        if (is_bool($value)) {
            return $html
                ? ($value ? '<span class="badge resolved">✓</span>' : '<span class="dim">—</span>')
                : ($value ? 'Yes' : 'No');
        }

        if ($field?->type === 'select' && isset($field->options[$value])) {
            return $html
                ? '<span class="badge">' . e($field->options[$value]) . '</span>'
                : $field->options[$value];
        }

        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        // Minor-unit money columns are stored as integers named `*_minor`.
        if (Str::endsWith($attribute, '_minor') && is_numeric($value)) {
            return number_format($value / 100, 2);
        }

        if (is_numeric($value) && Str::contains($attribute, ['amount', 'rate', 'total', 'quantity'])) {
            return rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
        }

        return Str::limit((string) $value, 60);
    }
}
