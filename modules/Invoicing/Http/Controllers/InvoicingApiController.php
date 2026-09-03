<?php

namespace Modules\Invoicing\Http\Controllers;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Invoicing\Models\Document;
use Modules\Invoicing\Models\Item;
use Modules\Invoicing\Models\Money;

class InvoicingApiController extends ApiController
{
    public function items(Request $request): JsonResponse
    {
        return $this->json([
            'items' => Item::where('organization_id', $this->orgId($request))
                ->search($request->query('q'))
                ->orderBy('name')
                ->get()
                ->map(fn (Item $i) => $i->toApi())
                ->values(),
        ]);
    }

    public function documents(Request $request): JsonResponse
    {
        return $this->json([
            'documents' => Document::where('organization_id', $this->orgId($request))
                ->when($request->query('type'), fn ($q, $t) => $q->where('doc_type', $t))
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->orderByDesc('issue_date')
                ->limit(min((int) $request->query('limit', 100), 500))
                ->get()
                ->map(fn (Document $d) => $d->toApi())
                ->values(),
        ]);
    }

    /** The full document, with its lines and payments — what a PDF renderer needs. */
    public function show(Request $request, string $document): JsonResponse
    {
        $doc = Document::where('organization_id', $this->orgId($request))
            ->with('lines', 'payments', 'customer')
            ->find($document);

        if (! $doc) {
            return $this->fail('Not found', 404);
        }

        return $this->json($doc->toApi() + [
            'customer' => $doc->customer?->toApi(),
            'lines' => $doc->lines->map(fn ($l) => [
                'name' => $l->name,
                'description' => $l->description,
                'quantity' => $l->quantity,
                'rate' => Money::toDecimal($l->rate_minor),
                'tax_percent' => $l->tax_percent,
                'amount' => Money::toDecimal($l->amount_minor),
            ])->values(),
            'payments' => $doc->payments->map(fn ($p) => [
                'amount' => Money::toDecimal($p->amount_minor),
                'paid_on' => $p->paid_on?->toDateString(),
                'method' => $p->method,
                'reference' => $p->reference,
            ])->values(),
        ]);
    }

    private function orgId(Request $request): ?string
    {
        $user = $request->attributes->get('auth_user') ?? $request->user();

        return $user?->organization_id;
    }
}
