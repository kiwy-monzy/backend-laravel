<?php

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Crm\Models\Customer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CustomerController extends ModuleController
{
    protected string $module = 'crm';

    public function index(Request $request)
    {
        $customers = $this->scopedToOrg(Customer::query())
            ->search($request->query('q'))
            ->when($request->query('type'), fn ($q, $type) => $q->where('contact_type', $type))
            ->orderBy('display_name')
            ->paginate(30)
            ->withQueryString();

        return view('crm::customers.index', [
            'customers' => $customers,
            'q' => $request->query('q'),
            'type' => $request->query('type'),
            'organization' => $this->organization(),
            'mayAdd' => $this->may('add'),
            'mayDelete' => $this->may('delete'),
            'gridColumns' => $this->grid()->spec(),
        ]);
    }

    /** The same list as JSON, for the data grid. */
    public function data(Request $request)
    {
        return $this->grid($request->query('type'))->json($request);
    }

    /**
     * What the grid shows for a contact.
     *
     * Declared once and used for both the header and the rows, so the two
     * cannot drift apart.
     */
    private function grid(?string $type = null): \App\Support\GridSource
    {
        $query = $this->scopedToOrg(Customer::query())
            ->when($type, fn ($q) => $q->where('contact_type', $type))
            ->orderBy('display_name');

        return \App\Support\GridSource::make($query, [
            'display_name' => ['title' => __('Name'), 'width' => 220],
            'contact_type' => [
                'title' => __('Type'), 'type' => 'badge', 'width' => 110,
                'value' => fn ($c) => Customer::TYPES[$c->contact_type] ?? $c->contact_type,
            ],
            'company_name' => ['title' => __('Company'), 'width' => 200],
            'email' => ['title' => __('Email'), 'width' => 230],
            'phone' => ['title' => __('Phone'), 'width' => 150],
            'billing_city' => ['title' => __('City'), 'width' => 130],
            'active' => ['title' => __('Active'), 'type' => 'boolean', 'width' => 90],
        ], ['display_name', 'company_name', 'email', 'phone']);
    }

    public function create()
    {
        $this->authorizeAction('add');

        return view('crm::customers.form', [
            'customer' => new Customer([
                'contact_type' => 'customer',
                'currency' => $this->organization()?->currency ?? 'TZS',
                'payment_terms' => 'due_on_receipt',
                'active' => true,
            ]),
            'organization' => $this->organization(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAction('add');

        $customer = Customer::create($this->validated($request) + [
            'id' => (string) Str::uuid(),
            'organization_id' => $this->organizationId(),
        ]);

        return redirect()
            ->route('crm.customers.edit', $customer)
            ->with('status', __('Customer created.'));
    }

    public function edit(string $customer)
    {
        return view('crm::customers.form', [
            'customer' => $this->find($customer),
            'organization' => $this->organization(),
        ]);
    }

    public function update(Request $request, string $customer): RedirectResponse
    {
        $this->authorizeAction('edit');

        $this->find($customer)->update($this->validated($request));

        return back()->with('status', __('Customer saved.'));
    }

    public function destroy(string $customer): RedirectResponse
    {
        $this->authorizeAction('delete');

        $this->find($customer)->delete();

        return redirect()->route('crm.customers.index')->with('status', __('Customer deleted.'));
    }

    /**
     * Look up by id *within this organization*.
     *
     * Not route-model binding: that resolves on the primary key alone and
     * would happily hand one organization another's customer record.
     */
    private function find(string $id): Customer
    {
        $customer = $this->scopedToOrg(Customer::query())->find($id);

        if (! $customer) {
            throw new NotFoundHttpException('No such customer.');
        }

        return $customer;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'contact_type' => ['required', 'in:customer,vendor'],
            'display_name' => ['required', 'string', 'max:190'],
            'company_name' => ['nullable', 'string', 'max:190'],
            'salutation' => ['nullable', 'string', 'max:20'],
            'first_name' => ['nullable', 'string', 'max:90'],
            'last_name' => ['nullable', 'string', 'max:90'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'mobile' => ['nullable', 'string', 'max:60'],
            'website' => ['nullable', 'string', 'max:190'],
            'currency' => ['required', 'string', 'max:8'],
            'payment_terms' => ['required', 'in:' . implode(',', array_keys(Customer::PAYMENT_TERMS))],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'tax_number' => ['nullable', 'string', 'max:60'],
            'billing_street' => ['nullable', 'string', 'max:190'],
            'billing_city' => ['nullable', 'string', 'max:90'],
            'billing_state' => ['nullable', 'string', 'max:90'],
            'billing_postcode' => ['nullable', 'string', 'max:32'],
            'billing_country' => ['nullable', 'string', 'max:90'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'active' => ['nullable', 'boolean'],
        ]) + ['active' => (bool) $request->boolean('active')];
    }
}
