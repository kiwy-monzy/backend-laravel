<?php

namespace Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Illuminate\Http\Request;
use Modules\Invoicing\Models\Document;

/**
 * The inbound half of the document book: purchase orders and supplier bills.
 *
 * These are the same `invoicing_documents` rows the sales side uses — one table
 * because the only real difference is which way the money points. They are
 * listed here, under Procurement, because that is where someone raising a
 * purchase order is working; the route stays inside this module so the
 * sub-rail does not jump to Invoicing mid-task.
 */
class ProcurementDocumentController extends ModuleController
{
    protected string $module = 'procurement';

    public function index(Request $request)
    {
        $type = $request->query('type', 'purchase_order');

        // Only the inbound types belong here; anything else is a sales document.
        abort_unless(in_array($type, Document::INBOUND, true), 404);

        return view('invoicing::invoices.index', [
            'documents' => $this->scopedToOrg(Document::query())
                ->where('doc_type', $type)
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->with('customer')
                ->orderByDesc('issue_date')
                ->paginate(30)
                ->withQueryString(),
            'type' => $type,
            'status' => $request->query('status'),
            'organization' => $this->organization(),
            'mayAdd' => $this->may('add'),
            'mayDelete' => $this->may('delete'),
            // Filter links stay here; the document form lives in Invoicing.
            'routeBase' => 'procurement.documents',
            'formRouteBase' => 'invoicing.invoices',
            'types' => array_intersect_key(Document::TYPES, array_flip(Document::INBOUND)),
        ]);
    }
}
