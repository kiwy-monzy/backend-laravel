<?php

namespace Modules\Invoicing\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Modules\Invoicing\Models\Document;
use Modules\Invoicing\Models\Item;
use Modules\Invoicing\Models\Money;

class InvoicingController extends ModuleController
{
    protected string $module = 'invoicing';

    public function index()
    {
        $documents = $this->scopedToOrg(Document::query());

        // Summed in SQL. Loading every live document to add up its balance was
        // fine on a demo and read a whole year's invoicing into memory here —
        // the balance is `total - paid`, which the database can add itself.
        $outstanding = (clone $documents)
            ->whereNotIn('status', ['void', 'draft'])
            ->sum(\Illuminate\Support\Facades\DB::raw('total_minor - paid_minor'));

        return view('invoicing::index', [
            'organization' => $this->organization(),
            'counts' => [
                'invoices' => (clone $documents)->where('doc_type', 'invoice')->count(),
                'estimates' => (clone $documents)->where('doc_type', 'estimate')->count(),
                'items' => $this->scopedToOrg(Item::query())->count(),
            ],
            'money' => [
                'billed' => Money::format((int) (clone $documents)->where('doc_type', 'invoice')->sum('total_minor'), $this->currency()),
                'collected' => Money::format((int) (clone $documents)->sum('paid_minor'), $this->currency()),
                'outstanding' => Money::format((int) $outstanding, $this->currency()),
            ],
            'overdue' => (clone $documents)
                ->whereNotIn('status', ['void', 'paid', 'draft'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now())
                ->with('customer')
                ->orderBy('due_date')
                ->limit(8)
                ->get(),
            'recent' => (clone $documents)->with('customer')->orderByDesc('created_at')->limit(8)->get(),
        ]);
    }

    private function currency(): string
    {
        return $this->organization()?->currency ?? 'TZS';
    }
}
