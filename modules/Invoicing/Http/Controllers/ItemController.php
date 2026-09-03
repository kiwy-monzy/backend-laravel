<?php

namespace Modules\Invoicing\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Invoicing\Models\Item;
use Modules\Invoicing\Models\Money;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ItemController extends ModuleController
{
    protected string $module = 'invoicing';

    public function index(Request $request)
    {
        return view('invoicing::items.index', [
            'items' => $this->scopedToOrg(Item::query())
                ->search($request->query('q'))
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
            'q' => $request->query('q'),
            'organization' => $this->organization(),
            'mayAdd' => $this->may('add'),
            'mayDelete' => $this->may('delete'),
        ]);
    }

    public function create()
    {
        $this->authorizeAction('add');

        return view('invoicing::items.form', [
            'item' => new Item(['item_type' => 'service', 'unit' => 'unit', 'active' => true]),
            'organization' => $this->organization(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAction('add');

        $item = Item::create($this->validated($request) + [
            'id' => (string) Str::uuid(),
            'organization_id' => $this->organizationId(),
        ]);

        return redirect()->route('invoicing.items.edit', $item)->with('status', __('Item created.'));
    }

    public function edit(string $item)
    {
        return view('invoicing::items.form', [
            'item' => $this->find($item),
            'organization' => $this->organization(),
        ]);
    }

    public function update(Request $request, string $item): RedirectResponse
    {
        $this->authorizeAction('edit');
        $this->find($item)->update($this->validated($request));

        return back()->with('status', __('Item saved.'));
    }

    public function destroy(string $item): RedirectResponse
    {
        $this->authorizeAction('delete');
        $this->find($item)->delete();

        return redirect()->route('invoicing.items.index')->with('status', __('Item deleted.'));
    }

    private function find(string $id): Item
    {
        $item = $this->scopedToOrg(Item::query())->find($id);

        if (! $item) {
            throw new NotFoundHttpException('No such item.');
        }

        return $item;
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'sku' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            'item_type' => ['required', 'in:service,goods'],
            'google_category' => ['nullable', 'string', 'max:60'],
            'unit' => ['nullable', 'string', 'max:30'],
            'rate' => ['required', 'numeric', 'min:0'],
            'purchase_rate' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'between:0,100'],
            'track_inventory' => ['nullable', 'boolean'],
            'stock_on_hand' => ['nullable', 'numeric'],
            'active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => $data['name'],
            'sku' => $data['sku'] ?? null,
            'description' => $data['description'] ?? null,
            'item_type' => $data['item_type'],
            'google_category' => $data['google_category'] ?? null,
            'unit' => $data['unit'] ?: 'unit',
            'rate_minor' => Money::toMinor($data['rate']),
            'purchase_rate_minor' => Money::toMinor($data['purchase_rate'] ?? 0),
            'tax_percent' => (float) ($data['tax_percent'] ?? 0),
            // Only goods can carry stock; a service with a stock count is a
            // number nobody can ever reconcile.
            'track_inventory' => $data['item_type'] === 'goods' && $request->boolean('track_inventory'),
            'stock_on_hand' => (float) ($data['stock_on_hand'] ?? 0),
            'active' => $request->boolean('active'),
        ];
    }
}
