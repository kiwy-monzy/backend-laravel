<?php

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Crm\Models\Customer;

/**
 * The module's JSON API.
 *
 * Separate class from the web controller on purpose: the two disagree about
 * almost everything that matters — one redirects and flashes, the other
 * returns status codes — and sharing a controller between them is how you end
 * up with `if ($request->expectsJson())` scattered through the actions.
 */
class CrmApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user') ?? $request->user();

        $customers = Customer::query()
            ->where('organization_id', $user?->organization_id)
            ->search($request->query('q'))
            ->orderBy('display_name')
            ->limit(min((int) $request->query('limit', 100), 500))
            ->get()
            ->map(fn (Customer $c) => $c->toApi())
            ->values();

        return $this->json(['customers' => $customers]);
    }

    public function show(Request $request, string $customer): JsonResponse
    {
        $user = $request->attributes->get('auth_user') ?? $request->user();

        $row = Customer::where('organization_id', $user?->organization_id)->find($customer);

        return $row ? $this->json($row->toApi()) : $this->fail('Not found', 404);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user') ?? $request->user();
        $data = $this->body($request);

        if (trim($data['display_name'] ?? '') === '') {
            return $this->fail('display_name required', 400);
        }

        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $user?->organization_id,
            'contact_type' => $data['contact_type'] ?? 'customer',
            'display_name' => $data['display_name'],
            'company_name' => $data['company_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'currency' => $data['currency'] ?? 'TZS',
            'payment_terms' => $data['payment_terms'] ?? 'due_on_receipt',
            'active' => true,
        ]);

        return $this->json($customer->toApi());
    }
}
