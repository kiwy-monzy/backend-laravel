<?php

namespace Modules\Invoicing\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Crm\Models\Customer;
use Modules\Invoicing\Models\Document;
use Modules\Invoicing\Models\DocumentLine;
use Modules\Invoicing\Models\Item;
use Modules\Invoicing\Models\Money;
use Modules\Invoicing\Models\Payment;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocumentController extends ModuleController
{
    protected string $module = 'invoicing';

    public function index(Request $request)
    {
        $type = $request->query('type', 'invoice');
        $status = $request->query('status');

        return view('invoicing::invoices.index', [
            'documents' => $this->scopedToOrg(Document::query())
                ->where('doc_type', $type)
                ->when($status, fn ($q) => $q->where('status', $status))
                ->with('customer')
                ->orderByDesc('issue_date')
                ->paginate(30)
                ->withQueryString(),
            'type' => $type,
            'status' => $status,
            'organization' => $this->organization(),
            'mayAdd' => $this->may('add'),
            'mayDelete' => $this->may('delete'),
            'gridColumns' => $this->grid($type)->spec(),
            'gridSource' => route('invoicing.invoices.data', ['type' => $type]),
        ]);
    }

    /** The document list as JSON, for the grid. */
    public function data(Request $request)
    {
        return $this->grid($request->query('type', 'invoice'))->json($request);
    }

    /**
     * The grid's columns for one document type.
     *
     * Built per type rather than once, because the columns a document has are
     * a property of what it is: a sales receipt was paid on issue, so it has
     * neither a due date nor a balance, and a bill is with a supplier.
     */
    private function grid(string $type): \App\Support\GridSource
    {
        $currency = $this->organization()?->currency ?? 'TZS';

        $columns = [
            'number' => ['title' => __('Number'), 'width' => 140, 'mono' => true],
            'customer' => [
                'title' => Document::partyLabelFor($type), 'width' => 220, 'sort' => 'customer_id',
                'value' => fn ($d) => $d->customer?->display_name ?? '—',
            ],
            'issue_date' => [
                'title' => __('Issued'), 'type' => 'date', 'width' => 110,
                'value' => fn ($d) => $d->issue_date?->toDateString(),
            ],
        ];

        if ($due = Document::dueLabelFor($type)) {
            $columns['due_date'] = [
                'title' => $due, 'type' => 'date', 'width' => 110,
                'value' => fn ($d) => $d->due_date?->toDateString() ?? '—',
            ];
        }

        $columns['total_minor'] = [
            'title' => Document::totalLabelFor($type), 'type' => 'money', 'width' => 150,
            'value' => fn ($d) => Money::format((int) $d->total_minor, $currency),
        ];

        if (Document::showsBalanceFor($type)) {
            $columns['balance'] = [
                'title' => __('Balance'), 'type' => 'money', 'width' => 150, 'sort' => 'paid_minor',
                'value' => fn ($d) => Money::format((int) $d->total_minor - (int) $d->paid_minor, $currency),
            ];
        }

        $columns['status'] = [
            'title' => __('Status'), 'type' => 'badge', 'width' => 130,
            'value' => fn ($d) => $d->statusLabel(),
        ];

        return \App\Support\GridSource::make(
            $this->scopedToOrg(Document::query())
                ->where('doc_type', $type)
                ->with('customer')
                ->orderByDesc('issue_date'),
            $columns,
            ['number', 'reference'],
        );
    }

    public function create(Request $request)
    {
        $this->authorizeAction('add');

        $type = $request->query('type', 'invoice');

        return view('invoicing::invoices.form', [
            'document' => new Document([
                'doc_type' => $type,
                'status' => 'draft',
                'issue_date' => now(),
                'due_date' => now()->addDays(30),
                'currency' => $this->organization()?->currency ?? 'TZS',
            ]),
            'customers' => $this->customers(),
            'items' => $this->items(),
            'organization' => $this->organization(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAction('add');

        $data = $this->validated($request);

        $document = DB::transaction(function () use ($data, $request) {
            $document = Document::create([
                'id' => (string) Str::uuid(),
                'organization_id' => $this->organizationId(),
                'doc_type' => $data['doc_type'],
                'number' => Document::nextNumber($this->organizationId(), $data['doc_type']),
                'status' => 'draft',
                'customer_id' => $data['customer_id'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'currency' => $data['currency'],
                'discount_minor' => Money::toMinor($data['discount'] ?? 0),
                'reference' => $data['reference'],
                'notes' => $data['notes'],
                'terms' => $data['terms'],
            ]);

            $this->syncLines($document, $request->input('lines', []));

            return $document->fresh('lines')->recalculate();
        });

        return redirect()->route('invoicing.invoices.edit', $document)
            ->with('status', __('Created :number.', ['number' => $document->number]));
    }

    public function edit(string $document)
    {
        $document = $this->find($document);

        return view('invoicing::invoices.form', [
            'document' => $document->load('lines', 'payments'),
            'customers' => $this->customers(),
            'items' => $this->items(),
            'organization' => $this->organization(),
        ]);
    }

    public function update(Request $request, string $document): RedirectResponse
    {
        $this->authorizeAction('edit');

        $doc = $this->find($document);
        $data = $this->validated($request);

        DB::transaction(function () use ($doc, $data, $request) {
            $doc->update([
                'customer_id' => $data['customer_id'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'currency' => $data['currency'],
                'discount_minor' => Money::toMinor($data['discount'] ?? 0),
                'reference' => $data['reference'],
                'notes' => $data['notes'],
                'terms' => $data['terms'],
            ]);

            $this->syncLines($doc, $request->input('lines', []));
            $doc->load('lines')->recalculate();
        });

        return back()->with('status', __('Saved :number.', ['number' => $doc->number]));
    }

    /** Send: the transition out of draft, which is an approve-level action. */
    public function send(string $document): RedirectResponse
    {
        $this->authorizeAction('approve');

        $doc = $this->find($document);

        if ($doc->status !== 'draft') {
            return back()->with('error', __('Only a draft can be sent.'));
        }

        $doc->update(['status' => 'sent']);

        return back()->with('status', __(':number marked as sent.', ['number' => $doc->number]));
    }

    public function void(string $document): RedirectResponse
    {
        $this->authorizeAction('approve');

        $doc = $this->find($document);

        if ($doc->paid_minor > 0) {
            return back()->with('error', __('A document with payments against it cannot be voided.'));
        }

        $doc->update(['status' => 'void']);

        return back()->with('status', __(':number voided.', ['number' => $doc->number]));
    }

    public function addPayment(Request $request, string $document): RedirectResponse
    {
        $this->authorizeAction('edit');

        $doc = $this->find($document);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_on' => ['required', 'date'],
            'method' => ['required', 'in:' . implode(',', array_keys(Payment::METHODS))],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $amount = Money::toMinor($data['amount']);

        // Overpayment is almost always a typo, and it silently turns the
        // balance negative on every report that reads it.
        if ($amount > $doc->balanceMinor()) {
            return back()->with('error', __('That is more than the outstanding balance of :balance.', [
                'balance' => $doc->formattedBalance(),
            ]));
        }

        Payment::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->organizationId(),
            'document_id' => $doc->id,
            'customer_id' => $doc->customer_id,
            'amount_minor' => $amount,
            'paid_on' => $data['paid_on'],
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $doc->load('lines')->recalculate();

        return back()->with('status', __('Payment recorded.'));
    }

    public function destroy(string $document): RedirectResponse
    {
        $this->authorizeAction('delete');

        $doc = $this->find($document);

        DB::transaction(function () use ($doc) {
            $doc->lines()->delete();
            $doc->payments()->delete();
            $doc->delete();
        });

        return redirect()->route('invoicing.invoices.index')->with('status', __('Deleted.'));
    }

    /** Replace the line set wholesale — the form posts the whole table. */
    private function syncLines(Document $document, array $lines): void
    {
        $document->lines()->delete();

        $position = 0;
        foreach ($lines as $line) {
            $name = trim($line['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $quantity = (float) ($line['quantity'] ?? 1);
            $rateMinor = Money::toMinor($line['rate'] ?? 0);

            DocumentLine::create([
                'document_id' => $document->id,
                // Every key here is `??`-guarded: the line rows are posted as a
                // raw array, so a client that omits a field (or a row cloned
                // before its hidden input was filled) must not fatal the save.
                'item_id' => ($line['item_id'] ?? '') ?: null,
                'name' => $name,
                'description' => $line['description'] ?? null,
                'quantity' => $quantity,
                'rate_minor' => $rateMinor,
                'tax_percent' => (float) ($line['tax_percent'] ?? 0),
                'amount_minor' => (int) round($quantity * $rateMinor),
                'position' => $position++,
            ]);
        }
    }

    private function find(string $id): Document
    {
        $document = $this->scopedToOrg(Document::query())->find($id);

        if (! $document) {
            throw new NotFoundHttpException('No such document.');
        }

        return $document;
    }

    /**
     * The form no longer receives these lists.
     *
     * Customers and items are chosen through typeahead pickers that query
     * `/admin/lookup/{source}` as the user types, so the form ships none of
     * them. Loading every row to render a `<select>` cost four and a half
     * thousand DOM nodes on a real organization and grew with the business.
     * The empty collections keep the view's contract intact.
     */
    private function customers()
    {
        return collect();
    }

    private function items()
    {
        return collect();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'doc_type' => ['required', 'in:' . implode(',', array_keys(Document::TYPES))],
            'customer_id' => ['nullable', 'string'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'currency' => ['required', 'string', 'max:8'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'terms' => ['nullable', 'string', 'max:4000'],
        ]);

        // `validate()` omits a `nullable` key entirely when the field was not
        // submitted — an empty <select> posts nothing at all — so the callers
        // would read an undefined index rather than a null. Filling the shape
        // here keeps every consumer able to assume the keys exist.
        return $data + [
            'customer_id' => null,
            'due_date' => null,
            'discount' => 0,
            'reference' => null,
            'notes' => null,
            'terms' => null,
        ];
    }
}
