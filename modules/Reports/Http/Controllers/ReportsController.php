<?php

namespace Modules\Reports\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Modules\Crm\Models\Customer;
use Modules\Expenses\Models\Expense;
use Modules\Insights\Support\Finance;
use Modules\Inventory\Models\Stock;
use Modules\Invoicing\Models\Document;
use Modules\Invoicing\Models\Money;

/**
 * Reads across the other modules and answers business questions.
 *
 * **Reports own no data.** Everything here is a query over Invoicing,
 * Expenses, Inventory and CRM — which is the point: profit is not a column
 * anywhere, it is invoicing minus cost minus expenses, and the only honest
 * place to compute it is somewhere that can see all three.
 *
 * A module the organization has not been granted is simply left out of the
 * arithmetic rather than throwing, so an organization on Starter still gets a
 * profit figure — one without inventory cost in it, clearly labelled.
 */
class ReportsController extends ModuleController
{
    protected string $module = 'reports';

    public function index()
    {
        return view('reports::index', [
            'organization' => $this->organization(),
            'available' => $this->availableSources(),
        ]);
    }

    /** Profit and loss: revenue, cost of goods, expenses, what is left. */
    public function financial(Request $request)
    {
        $range = DateRange::make($request->query('range'));
        $currency = $this->currency();

        $invoices = $this->has('invoicing')
            ? $range->apply($this->scopedToOrg(Document::query()), 'issue_date')
                ->where('doc_type', 'invoice')
                ->whereNot('status', 'void')
            : null;

        $revenue = $invoices ? (int) (clone $invoices)->sum('total_minor') : 0;
        $collected = $invoices ? (int) (clone $invoices)->sum('paid_minor') : 0;
        $outstanding = $revenue - $collected;

        $expenses = $this->has('expenses')
            ? (int) $range->apply($this->scopedToOrg(Expense::query()), 'spent_on')
                ->whereNot('status', 'rejected')
                ->sum('amount_minor')
            : 0;

        // Cost of goods is the stock value consumed, which this schema does
        // not track per sale — so it is reported as stock on hand at cost and
        // labelled as such rather than silently folded into profit.
        $stockValue = $this->has('inventory')
            ? (int) $this->scopedToOrg(Stock::query())
                ->selectRaw('COALESCE(SUM(quantity * unit_cost_minor), 0) as v')
                ->value('v')
            : 0;

        return view('reports::financial', [
            'organization' => $this->organization(),
            'range' => $range,
            'currency' => $currency,
            'rows' => [
                ['Revenue (invoiced)', $revenue, false],
                ['Cash collected', $collected, false],
                ['Outstanding', $outstanding, false],
                ['Operating expenses', -$expenses, false],
                ['Net (revenue less expenses)', $revenue - $expenses, true],
            ],
            'stockValue' => $stockValue,
            'invoiceCount' => $invoices ? (clone $invoices)->count() : 0,
            'has' => $this->availableSources(),
        ]);
    }

    /** What was invoiced, month by month. */
    public function sales(Request $request)
    {
        $range = DateRange::make($request->query('range'));

        $documents = $this->has('invoicing')
            ? $range->apply($this->scopedToOrg(Document::query()), 'issue_date')
                ->where('doc_type', 'invoice')
                ->whereNot('status', 'void')
                ->with('customer')
                ->orderByDesc('issue_date')
                ->get()
            : collect();

        return view('reports::sales', [
            'organization' => $this->organization(),
            'range' => $range,
            'currency' => $this->currency(),
            'documents' => $documents,
            'total' => (int) $documents->sum('total_minor'),
            'byStatus' => $documents->groupBy('status')->map(fn ($rows) => [
                'count' => $rows->count(),
                'total' => (int) $rows->sum('total_minor'),
            ]),
            'byMonth' => $documents
                ->groupBy(fn (Document $d) => $d->issue_date?->format('Y-m'))
                ->map(fn ($rows) => (int) $rows->sum('total_minor'))
                ->sortKeys(),
            'has' => $this->availableSources(),
        ]);
    }

    /** Who is worth the most, and who owes. */
    public function customers(Request $request)
    {
        $range = DateRange::make($request->query('range'));

        $documents = $this->has('invoicing')
            ? $range->apply($this->scopedToOrg(Document::query()), 'issue_date')
                ->where('doc_type', 'invoice')
                ->whereNot('status', 'void')
                ->get()
            : collect();

        $names = Customer::where('organization_id', $this->organizationId())
            ->pluck('display_name', 'id');

        $rows = $documents
            ->groupBy('customer_id')
            ->map(fn ($docs, $id) => [
                'name' => $names[$id] ?? __('(no customer)'),
                'invoices' => $docs->count(),
                'billed' => (int) $docs->sum('total_minor'),
                'paid' => (int) $docs->sum('paid_minor'),
                'owing' => (int) $docs->sum(fn (Document $d) => $d->balanceMinor()),
            ])
            ->sortByDesc('billed')
            ->values();

        return view('reports::customers', [
            'organization' => $this->organization(),
            'range' => $range,
            'currency' => $this->currency(),
            'rows' => $rows,
            'customerCount' => Customer::where('organization_id', $this->organizationId())->count(),
            'has' => $this->availableSources(),
        ]);
    }

    /** What is on the shelf, and what is running out. */
    public function inventory()
    {
        if (! $this->has('inventory')) {
            return view('reports::inventory', [
                'organization' => $this->organization(),
                'currency' => $this->currency(),
                'stock' => collect(), 'value' => 0, 'low' => collect(), 'lines' => 0,
                'has' => $this->availableSources(),
            ]);
        }

        // Totals in SQL. Reading every stock row to multiply it in PHP is fine
        // for a store cupboard and reads twenty-five thousand rows into memory
        // for a real one — and the page only ever prints the two numbers.
        $value = (int) $this->scopedToOrg(Stock::query())
            ->sum(\Illuminate\Support\Facades\DB::raw('quantity * unit_cost_minor'));

        // "Below reorder level" is the only line on this page anyone acts on
        // the same day they read it, so that is what the table lists — the full
        // stock ledger lives in the Inventory module.
        $low = $this->scopedToOrg(Stock::query())
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->where('reorder_level', '>', 0)
            ->with('item')
            ->orderBy('quantity')
            ->limit(100)
            ->get();

        return view('reports::inventory', [
            'organization' => $this->organization(),
            'currency' => $this->currency(),
            'stock' => $low,
            'value' => $value,
            'low' => $low,
            'lines' => $this->scopedToOrg(Stock::query())->count(),
            'has' => $this->availableSources(),
        ]);
    }

    /** Which source modules this organization can actually draw on. */
    private function availableSources(): array
    {
        return [
            'invoicing' => $this->has('invoicing'),
            'expenses' => $this->has('expenses'),
            'inventory' => $this->has('inventory'),
            'crm' => $this->has('crm'),
        ];
    }

    private function has(string $module): bool
    {
        return (bool) $this->organization()?->allowsModule($this->role(), $module);
    }

    private function currency(): string
    {
        return $this->organization()?->currency ?? 'TZS';
    }
}
