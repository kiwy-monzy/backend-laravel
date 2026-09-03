<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Allocates the next reference for a thing, per organization.
 *
 * Every generated code in the product comes through here, so they all have the
 * same shape and none of them is typed by a user. Allocation is a single
 * `UPDATE … SET next_number = next_number + 1` inside a transaction rather than
 * a read-then-write, because two people saving at the same moment must not be
 * handed the same number.
 */
final class Sequences
{
    /**
     * The things that get numbered, with the defaults a new organization starts
     * on. Owners can change the prefix and width; the keys are fixed, because
     * they are what the code asks for.
     */
    public const KINDS = [
        'expense' => ['label' => 'Expenses', 'prefix' => 'EXP', 'padding' => 6],
        'asset' => ['label' => 'Assets', 'prefix' => 'FGE', 'padding' => 6],
        'ticket' => ['label' => 'Support tickets', 'prefix' => 'TKT', 'padding' => 5],
        'order' => ['label' => 'Orders', 'prefix' => 'ORD', 'padding' => 6],
        'purchase_order' => ['label' => 'Purchase orders', 'prefix' => 'PO', 'padding' => 6],
        'purchase_request' => ['label' => 'Purchase requests', 'prefix' => 'PR', 'padding' => 6],
        'shipment' => ['label' => 'Shipments', 'prefix' => 'SHP', 'padding' => 6],
        'project' => ['label' => 'Projects', 'prefix' => 'PRJ', 'padding' => 4],
        'department' => ['label' => 'Departments', 'prefix' => 'DEP', 'padding' => 3],
        'account' => ['label' => 'Ledger accounts', 'prefix' => '', 'padding' => 4],
        'stock' => ['label' => 'Stock SKUs', 'prefix' => 'SKU', 'padding' => 5],
        'zone' => ['label' => 'Zones', 'prefix' => 'ZN', 'padding' => 3],
        'service_provider' => ['label' => 'Service providers', 'prefix' => 'SP', 'padding' => 4],
        'service_request' => ['label' => 'Service requests', 'prefix' => 'SR', 'padding' => 6],
        'service_booking' => ['label' => 'Service bookings', 'prefix' => 'SB', 'padding' => 6],
    ];

    /** The next reference for this organization, e.g. `EXP-000123`. */
    public static function next(?string $organizationId, string $key): string
    {
        if (! $organizationId) {
            return strtoupper($key) . '-' . now()->format('YmdHis');
        }

        $defaults = self::KINDS[$key] ?? ['prefix' => strtoupper($key), 'padding' => 5];

        return DB::transaction(function () use ($organizationId, $key, $defaults) {
            $row = DB::table('sequences')
                ->where('organization_id', $organizationId)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                DB::table('sequences')->insert([
                    'organization_id' => $organizationId,
                    'key' => $key,
                    'prefix' => $defaults['prefix'],
                    'next_number' => 2,
                    'padding' => $defaults['padding'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return self::format($defaults['prefix'], 1, $defaults['padding']);
            }

            DB::table('sequences')
                ->where('organization_id', $organizationId)
                ->where('key', $key)
                ->update(['next_number' => $row->next_number + 1, 'updated_at' => now()]);

            return self::format($row->prefix, $row->next_number, $row->padding);
        });
    }

    /** What the next reference *would* be, without consuming it. */
    public static function preview(?string $organizationId, string $key): string
    {
        $defaults = self::KINDS[$key] ?? ['prefix' => strtoupper($key), 'padding' => 5];

        $row = $organizationId
            ? DB::table('sequences')->where('organization_id', $organizationId)->where('key', $key)->first()
            : null;

        return self::format(
            $row->prefix ?? $defaults['prefix'],
            $row->next_number ?? 1,
            $row->padding ?? $defaults['padding'],
        );
    }

    private static function format(string $prefix, int $number, int $padding): string
    {
        $digits = str_pad((string) $number, max(1, $padding), '0', STR_PAD_LEFT);

        return $prefix === '' ? $digits : $prefix . '-' . $digits;
    }

    /** Every counter for an organization, filled with defaults where unset. */
    public static function all(?string $organizationId): array
    {
        $rows = $organizationId
            ? DB::table('sequences')->where('organization_id', $organizationId)->get()->keyBy('key')
            : collect();

        $out = [];

        foreach (self::KINDS as $key => $spec) {
            $row = $rows[$key] ?? null;
            $out[$key] = [
                'label' => $spec['label'],
                'prefix' => $row->prefix ?? $spec['prefix'],
                'padding' => $row->padding ?? $spec['padding'],
                'next_number' => $row->next_number ?? 1,
                'example' => self::format($row->prefix ?? $spec['prefix'], $row->next_number ?? 1, $row->padding ?? $spec['padding']),
            ];
        }

        return $out;
    }
}
