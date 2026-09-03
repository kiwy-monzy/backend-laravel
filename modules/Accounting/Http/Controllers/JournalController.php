<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Support\Books;
use Modules\Invoicing\Models\Money;

/**
 * The journal, and the books derived from it.
 *
 * Only the journal is written. Ledger, trial balance and statements are reads
 * over the same lines, so they cannot disagree with each other.
 */
class JournalController extends ModuleController
{
    protected string $module = 'accounting';

    public function index(Request $request)
    {
        return view('accounting::journal.index', [
            'entries' => $this->scopedToOrg(JournalEntry::query())
                ->with('lines.account')
                ->orderByDesc('entry_date')
                ->orderByDesc('created_at')
                ->paginate(25),
            'organization' => $this->organization(),
            'mayAdd' => $this->may('add'),
            'gridColumns' => $this->journalGrid()->spec(),
        ]);
    }

    /** The journal as JSON, for the grid. */
    public function journalData(Request $request)
    {
        return $this->journalGrid()->json($request);
    }

    /**
     * The journal as a table.
     *
     * Debits and credits are summed per entry rather than listed per line: the
     * journal page is for finding an entry, and the lines behind it belong on
     * the entry itself. The unbalanced flag is what makes the list worth
     * scanning — a journal is only trustworthy if nothing in it is crooked.
     */
    private function journalGrid(): \App\Support\GridSource
    {
        $currency = $this->organization()?->currency ?? 'TZS';

        return \App\Support\GridSource::make(
            $this->scopedToOrg(JournalEntry::query())
                ->with('lines')
                ->orderByDesc('entry_date')
                ->orderByDesc('created_at'),
            [
                'number' => ['title' => __('Entry'), 'width' => 120, 'mono' => true],
                'entry_date' => [
                    'title' => __('Date'), 'type' => 'date', 'width' => 110,
                    'value' => fn ($e) => $e->entry_date?->toDateString(),
                ],
                'memo' => ['title' => __('Memo'), 'width' => 260],
                'accounts' => [
                    'title' => __('Accounts'), 'width' => 130, 'icon' => 'number',
                    'value' => fn ($e) => $e->lines->count(),
                ],
                'debit' => [
                    'title' => __('Debit'), 'type' => 'money', 'width' => 150,
                    'value' => fn ($e) => Money::format($e->totalDebit(), $currency),
                ],
                'credit' => [
                    'title' => __('Credit'), 'type' => 'money', 'width' => 150,
                    'value' => fn ($e) => Money::format($e->totalCredit(), $currency),
                ],
                'balanced' => [
                    'title' => __('Balanced'), 'type' => 'boolean', 'width' => 100,
                    'value' => fn ($e) => $e->isBalanced(),
                ],
                'source' => ['title' => __('Source'), 'type' => 'badge', 'width' => 110],
            ],
            ['number', 'memo', 'reference'],
        );
    }

    public function create()
    {
        $this->authorizeAction('add');

        return view('accounting::journal.form', [
            'entry' => new JournalEntry(['entry_date' => now()]),
            'accounts' => $this->accounts(),
            'organization' => $this->organization(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAction('add');

        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            $entry = JournalEntry::create([
                'organization_id' => $this->organizationId(),
                'number' => JournalEntry::nextNumber($this->organizationId()),
                'entry_date' => $data['entry_date'],
                'memo' => $data['memo'],
                'reference' => $data['reference'],
                'source' => 'manual',
            ]);

            $this->syncLines($entry, $request->input('lines', []));
        });

        return redirect()->route('accounting.journal.index')
            ->with('status', __('Journal entry posted.'));
    }

    public function edit(string $id)
    {
        return view('accounting::journal.form', [
            'entry' => $this->findEntry($id)->load('lines'),
            'accounts' => $this->accounts(),
            'organization' => $this->organization(),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAction('edit');

        $entry = $this->findEntry($id);
        $data = $this->validated($request);

        DB::transaction(function () use ($entry, $data, $request) {
            $entry->update([
                'entry_date' => $data['entry_date'],
                'memo' => $data['memo'],
                'reference' => $data['reference'],
            ]);

            $entry->lines()->delete();
            $this->syncLines($entry, $request->input('lines', []));
        });

        return redirect()->route('accounting.journal.index')
            ->with('status', __('Journal entry updated.'));
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorizeAction('delete');

        $entry = $this->findEntry($id);

        DB::transaction(function () use ($entry) {
            $entry->lines()->delete();
            $entry->delete();
        });

        return redirect()->route('accounting.journal.index')
            ->with('status', __('Journal entry deleted.'));
    }

    /** The general ledger: every account, its movement and its balance. */
    public function ledger(Request $request)
    {
        [$from, $to] = $this->window($request);
        $accountId = $request->query('account');

        return view('accounting::journal.ledger', [
            'ledger' => Books::ledger($this->organizationId(), $from, $to),
            'accounts' => $this->accounts(),
            'accountId' => $accountId,
            // When one account is chosen, show the entries behind its balance.
            'lines' => $accountId
                ? JournalLine::query()
                    ->join('accounting_journal_entries as e', 'e.id', '=', 'accounting_journal_lines.entry_id')
                    ->where('e.organization_id', $this->organizationId())
                    ->where('accounting_journal_lines.account_id', $accountId)
                    ->when($from, fn ($q) => $q->whereDate('e.entry_date', '>=', $from))
                    ->when($to, fn ($q) => $q->whereDate('e.entry_date', '<=', $to))
                    ->orderBy('e.entry_date')
                    ->select('accounting_journal_lines.*', 'e.entry_date', 'e.number', 'e.memo as entry_memo')
                    ->get()
                : collect(),
            'from' => $from, 'to' => $to,
            'organization' => $this->organization(),
        ]);
    }

    public function trialBalance(Request $request)
    {
        [$from, $to] = $this->window($request);

        return view('accounting::journal.trial-balance', [
            'trial' => Books::trialBalance($this->organizationId(), $from, $to),
            'from' => $from, 'to' => $to,
            'organization' => $this->organization(),
        ]);
    }

    public function statements(Request $request)
    {
        [$from, $to] = $this->window($request);

        return view('accounting::journal.statements', [
            'statements' => Books::statements($this->organizationId(), $from, $to),
            'from' => $from, 'to' => $to,
            'organization' => $this->organization(),
        ]);
    }

    /** Assets on the books: the asset accounts and what they carry. */
    public function fixedAssets(Request $request)
    {
        $ledger = Books::ledger($this->organizationId())
            ->filter(fn ($l) => $l['account']->account_type === 'asset')
            ->values();

        return view('accounting::journal.fixed-assets', [
            'ledger' => $ledger,
            'total' => (int) $ledger->sum('balance'),
            'organization' => $this->organization(),
        ]);
    }

    /**
     * What each customer owes: billed less paid, from the invoicing documents.
     *
     * This is a customer sub-ledger rather than a journal read — the detail
     * lives with the documents, and duplicating it into the journal would mean
     * two versions of one truth.
     */
    public function customerLedger()
    {
        // Aggregated in SQL, not in PHP. Pulling every document into memory to
        // group it worked at a few hundred rows and fell over at twelve
        // thousand; the database can do this without materialising any of it.
        $rows = \Modules\Invoicing\Models\Document::query()
            ->where('invoicing_documents.organization_id', $this->organizationId())
            ->whereNotIn('doc_type', \Modules\Invoicing\Models\Document::INBOUND)
            ->where('status', '!=', 'void')
            ->leftJoin('crm_customers as c', 'c.id', '=', 'invoicing_documents.customer_id')
            ->groupBy('invoicing_documents.customer_id', 'c.display_name')
            ->selectRaw('c.display_name as customer_name,
                         COUNT(*) as documents,
                         SUM(total_minor) as billed,
                         SUM(paid_minor) as paid,
                         SUM(total_minor - paid_minor) as outstanding')
            ->orderByDesc('outstanding')
            // simplePaginate, not paginate: counting a grouped query means
            // wrapping it in SELECT COUNT(*) FROM (…) and running the whole
            // aggregation twice. The page shows portfolio totals of its own, so
            // a page count buys nothing for the second scan it costs.
            ->simplePaginate(50);

        // The headline is over every customer, not the page being shown.
        $outstanding = (int) \Modules\Invoicing\Models\Document::query()
            ->where('organization_id', $this->organizationId())
            ->whereNotIn('doc_type', \Modules\Invoicing\Models\Document::INBOUND)
            ->where('status', '!=', 'void')
            ->sum(\Illuminate\Support\Facades\DB::raw('total_minor - paid_minor'));

        return view('accounting::journal.customer-ledger', [
            'rows' => $rows,
            'outstanding' => $outstanding,
            'customerCount' => \Modules\Invoicing\Models\Document::query()
                ->where('organization_id', $this->organizationId())
                ->distinct()
                ->count('customer_id'),
            'organization' => $this->organization(),
        ]);
    }

    // ---- helpers ----------------------------------------------------------

    /** The reporting window, defaulting to the whole of this year. */
    private function window(Request $request): array
    {
        return [
            $request->query('from') ?: now()->startOfYear()->toDateString(),
            $request->query('to') ?: now()->endOfYear()->toDateString(),
        ];
    }

    private function accounts()
    {
        return $this->scopedToOrg(Account::query())->orderBy('code')->get();
    }

    private function findEntry(string $id): JournalEntry
    {
        $entry = $this->scopedToOrg(JournalEntry::query())->find($id);

        abort_unless($entry, 404);

        return $entry;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'entry_date' => ['required', 'date'],
            'memo' => ['nullable', 'string', 'max:250'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]) + ['memo' => null, 'reference' => null];
    }

    /** Writes the posted lines, dropping the blank rows the form always sends. */
    private function syncLines(JournalEntry $entry, array $lines): void
    {
        $position = 0;

        foreach ($lines as $line) {
            $accountId = $line['account_id'] ?? null;
            $debit = Money::toMinor($line['debit'] ?? 0);
            $credit = Money::toMinor($line['credit'] ?? 0);

            if (! $accountId || ($debit === 0 && $credit === 0)) {
                continue;
            }

            JournalLine::create([
                'entry_id' => $entry->id,
                'account_id' => $accountId,
                'debit_minor' => $debit,
                'credit_minor' => $credit,
                'memo' => $line['memo'] ?? null,
                'position' => $position++,
            ]);
        }
    }
}
