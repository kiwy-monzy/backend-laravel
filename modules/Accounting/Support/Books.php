<?php

namespace Modules\Accounting\Support;

use Illuminate\Support\Collection;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalLine;

/**
 * The derived books.
 *
 * Nothing here is stored. The ledger, the trial balance and the statements are
 * folds over the journal lines, recomputed on every read, so a figure on a
 * statement can never disagree with the entries behind it.
 *
 * Sign convention: assets and expenses are *debit-natured* (a debit increases
 * them); liabilities, equity and income are *credit-natured*. `balanceOf`
 * returns each account's balance in its own natural direction, which is what
 * makes a trial balance sum to zero and a statement read without minus signs.
 */
final class Books
{
    public const DEBIT_NATURED = ['asset', 'expense'];

    /** Every account with its movement and closing balance, in code order. */
    public static function ledger(?string $organizationId, ?string $from = null, ?string $to = null): Collection
    {
        $accounts = Account::where('organization_id', $organizationId)
            ->orderBy('code')
            ->get();

        $totals = self::totalsByAccount($organizationId, $from, $to);

        return $accounts->map(function (Account $account) use ($totals) {
            $t = $totals[$account->id] ?? ['debit' => 0, 'credit' => 0];
            $opening = (int) $account->opening_balance_minor;

            return [
                'account' => $account,
                'debit' => $t['debit'],
                'credit' => $t['credit'],
                'opening' => $opening,
                'balance' => $opening + self::natural($account->account_type, $t['debit'], $t['credit']),
            ];
        });
    }

    /** Debit and credit totals per account id, within an optional date window. */
    public static function totalsByAccount(?string $organizationId, ?string $from = null, ?string $to = null): array
    {
        $rows = JournalLine::query()
            ->join('accounting_journal_entries as e', 'e.id', '=', 'accounting_journal_lines.entry_id')
            ->where('e.organization_id', $organizationId)
            ->when($from, fn ($q) => $q->whereDate('e.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('e.entry_date', '<=', $to))
            ->groupBy('accounting_journal_lines.account_id')
            ->selectRaw('accounting_journal_lines.account_id, SUM(debit_minor) as d, SUM(credit_minor) as c')
            ->get();

        $out = [];

        foreach ($rows as $r) {
            $out[$r->account_id] = ['debit' => (int) $r->d, 'credit' => (int) $r->c];
        }

        return $out;
    }

    /** A movement expressed in the account type's natural direction. */
    public static function natural(string $type, int $debit, int $credit): int
    {
        return in_array($type, self::DEBIT_NATURED, true)
            ? $debit - $credit
            : $credit - $debit;
    }

    /**
     * The trial balance: every account's net, in the debit or credit column.
     *
     * The two columns must agree. When they do not, the journal contains an
     * unbalanced entry, and the page says so rather than quietly showing a
     * total nobody can reconcile.
     */
    public static function trialBalance(?string $organizationId, ?string $from = null, ?string $to = null): array
    {
        $rows = [];
        $debitTotal = 0;
        $creditTotal = 0;

        foreach (self::ledger($organizationId, $from, $to) as $line) {
            $balance = $line['balance'];

            if ($balance === 0) {
                continue;
            }

            $isDebitNatured = in_array($line['account']->account_type, self::DEBIT_NATURED, true);
            // A negative natural balance belongs in the opposite column.
            $debit = ($isDebitNatured xor $balance < 0) ? abs($balance) : 0;
            $credit = $debit === 0 ? abs($balance) : 0;

            $debitTotal += $debit;
            $creditTotal += $credit;

            $rows[] = ['account' => $line['account'], 'debit' => $debit, 'credit' => $credit];
        }

        return [
            'rows' => $rows,
            'debit_total' => $debitTotal,
            'credit_total' => $creditTotal,
            'balanced' => $debitTotal === $creditTotal,
        ];
    }

    /**
     * Profit and loss, and the balance sheet, from the same fold.
     *
     * Profit for the period is carried into equity on the balance sheet, which
     * is what makes it balance without a stored retained-earnings figure.
     */
    public static function statements(?string $organizationId, ?string $from = null, ?string $to = null): array
    {
        $ledger = self::ledger($organizationId, $from, $to);

        $by = fn (string $type) => $ledger->filter(fn ($l) => $l['account']->account_type === $type)->values();
        $sum = fn (Collection $rows) => (int) $rows->sum('balance');

        $income = $by('income');
        $expense = $by('expense');
        $asset = $by('asset');
        $liability = $by('liability');
        $equity = $by('equity');

        $profit = $sum($income) - $sum($expense);

        return [
            'income' => $income, 'income_total' => $sum($income),
            'expense' => $expense, 'expense_total' => $sum($expense),
            'profit' => $profit,
            'asset' => $asset, 'asset_total' => $sum($asset),
            'liability' => $liability, 'liability_total' => $sum($liability),
            'equity' => $equity, 'equity_total' => $sum($equity),
            // Assets = liabilities + equity + profit for the period.
            'balances' => $sum($asset) === $sum($liability) + $sum($equity) + $profit,
        ];
    }
}
