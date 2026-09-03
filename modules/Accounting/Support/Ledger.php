<?php

namespace Modules\Accounting\Support;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;

/**
 * Posting: how the rest of the product writes into the books.
 *
 * Until now the journal was only ever written by hand, so the accounts said
 * whatever somebody had typed and the rest of the system — twelve thousand
 * invoices, eight thousand payments, six thousand expenses — went unrecorded.
 * The books did not follow the money.
 *
 * Every posting goes through {@see post()}, which enforces the one rule that
 * makes a ledger a ledger: the lines must balance. An entry that does not is
 * refused rather than saved, because a journal you have to audit is not a
 * journal. Each entry remembers what caused it (`source_type`, `source_id`),
 * so a figure on a statement can be traced back to the invoice behind it, and
 * so posting the same document twice replaces its entry instead of doubling it.
 */
final class Ledger
{
    /**
     * The accounts a posting needs, by role rather than by code.
     *
     * An organization numbers its accounts however it likes, so the rules below
     * ask for "the bank account" and this maps that to whatever that
     * organization actually calls it — creating it on first use, so posting
     * never fails because a chart of accounts is incomplete.
     */
    private const ROLES = [
        'bank' => ['code' => '1000', 'name' => 'Bank', 'type' => 'asset'],
        'receivable' => ['code' => '1100', 'name' => 'Accounts receivable', 'type' => 'asset'],
        'inventory' => ['code' => '1200', 'name' => 'Inventory', 'type' => 'asset'],
        'payable' => ['code' => '2000', 'name' => 'Accounts payable', 'type' => 'liability'],
        'tax_payable' => ['code' => '2100', 'name' => 'VAT payable', 'type' => 'liability'],
        'sales' => ['code' => '4000', 'name' => 'Sales', 'type' => 'income'],
        'expense' => ['code' => '5000', 'name' => 'Operating expenses', 'type' => 'expense'],
    ];

    /**
     * Write one balanced entry, replacing any earlier entry for the same source.
     *
     * @param  array<int,array{account:string,debit?:int,credit?:int,memo?:string}>  $lines
     */
    public static function post(
        string $organizationId,
        string $sourceType,
        string $sourceId,
        string $date,
        string $memo,
        array $lines,
        ?string $reference = null,
    ): ?JournalEntry {
        $lines = array_values(array_filter(
            $lines,
            fn ($l) => (int) ($l['debit'] ?? 0) !== 0 || (int) ($l['credit'] ?? 0) !== 0,
        ));

        if ($lines === []) {
            return null;
        }

        $debit = array_sum(array_map(fn ($l) => (int) ($l['debit'] ?? 0), $lines));
        $credit = array_sum(array_map(fn ($l) => (int) ($l['credit'] ?? 0), $lines));

        if ($debit !== $credit) {
            throw new \RuntimeException(sprintf(
                'Refusing to post %s %s: debits %d do not equal credits %d.',
                $sourceType, $sourceId, $debit, $credit,
            ));
        }

        return DB::transaction(function () use ($organizationId, $sourceType, $sourceId, $date, $memo, $lines, $reference) {
            // Re-posting a document replaces its entry. Without this, editing an
            // invoice would add a second entry and the books would count it twice.
            $existing = JournalEntry::where('organization_id', $organizationId)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->first();

            if ($existing) {
                $existing->lines()->delete();
                $existing->delete();
            }

            $entry = JournalEntry::create([
                'organization_id' => $organizationId,
                'number' => JournalEntry::nextNumber($organizationId),
                'entry_date' => $date,
                'memo' => $memo,
                'reference' => $reference,
                'source' => $sourceType,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]);

            foreach ($lines as $position => $line) {
                JournalLine::create([
                    'entry_id' => $entry->id,
                    'account_id' => self::account($organizationId, $line['account'])->id,
                    'debit_minor' => (int) ($line['debit'] ?? 0),
                    'credit_minor' => (int) ($line['credit'] ?? 0),
                    'memo' => $line['memo'] ?? null,
                    'position' => $position,
                ]);
            }

            return $entry;
        });
    }

    /** Remove the entry a document caused, when the document goes away. */
    public static function unpost(string $organizationId, string $sourceType, string $sourceId): void
    {
        $entry = JournalEntry::where('organization_id', $organizationId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        if (! $entry) {
            return;
        }

        DB::transaction(function () use ($entry) {
            $entry->lines()->delete();
            $entry->delete();
        });
    }

    /**
     * The account for a role, created on first use.
     *
     * Matching on the role's conventional code first means an organization that
     * already has a "4000 Sales" keeps using it rather than gaining a second one.
     */
    public static function account(string $organizationId, string $role): Account
    {
        $spec = self::ROLES[$role] ?? throw new \InvalidArgumentException("Unknown ledger role: {$role}");

        $existing = Account::where('organization_id', $organizationId)
            ->where('code', $spec['code'])
            ->first();

        if ($existing) {
            return $existing;
        }

        return Account::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => $organizationId,
            'code' => $spec['code'],
            'name' => $spec['name'],
            'account_type' => $spec['type'],
            'currency' => 'TZS',
            'active' => true,
            'description' => 'Created automatically for posting.',
        ]);
    }
}
