<?php

namespace Modules\Invoicing\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Illuminate\Http\Request;
use Modules\Invoicing\Models\Payment;

/**
 * Money received, across every document.
 *
 * Payments are recorded against a document (that is where the allocation
 * belongs), but the question "what came in this month" is not answerable from
 * a document list — so the receipts get a page of their own, the way the
 * desktop app's Payments screen does.
 */
class PaymentController extends ModuleController
{
    protected string $module = 'invoicing';

    public function index(Request $request)
    {
        $method = $request->query('method');

        $filtered = fn () => $this->scopedToOrg(Payment::query())
            ->when($method, fn ($q) => $q->where('method', $method));

        return view('invoicing::payments.index', [
            'payments' => $filtered()
                ->with('document.customer')
                ->orderByDesc('paid_on')
                ->paginate(30)
                ->withQueryString(),
            'method' => $method,
            'organization' => $this->organization(),
            // The total answers the question the page exists for, so it is taken
            // over the whole filtered set rather than the page being shown.
            'total' => (int) $filtered()->sum('amount_minor'),
        ]);
    }
}
