<?php

namespace App\Support;

use App\Models\User;

/**
 * What the titlebar search looks through, across every module.
 *
 * **One declaration per searchable thing, not one query per module scattered
 * through a controller.** Each entry names the module it belongs to (so the
 * search can skip what the member cannot open), the model and columns to look
 * in, and how to turn a row into a result. The controller iterates this and
 * nothing else — adding a module to search is one array entry here.
 *
 * Every source is scoped to the organization by the controller, so the search
 * can never surface another tenant's records even when the class is loaded.
 */
final class SearchRegistry
{
    /**
     * @return array<int,array{
     *   module:string,
     *   model:class-string,
     *   columns:array<int,string>,
     *   kind:string,
     *   scope:string,
     *   title:callable,
     *   snippet:callable,
     *   route:callable,
     * }>
     */
    public static function sources(): array
    {
        $sources = [];

        // CRM — leads and customers, the two things a salesperson searches for
        // by name a dozen times a day.
        if (class_exists(\Modules\Crm\Models\Lead::class)) {
            $sources[] = [
                'module' => 'crm',
                'model' => \Modules\Crm\Models\Lead::class,
                'columns' => ['name', 'email', 'phone', 'company', 'subject'],
                'kind' => __('Lead'),
                'scope' => 'organization_id',
                'title' => fn ($r) => $r->name . ($r->company ? ' — ' . $r->company : ''),
                'snippet' => fn ($r) => $r->statusLabel() . ' · ' . $r->sourceLabel(),
                'route' => fn ($r) => route('crm.leads.edit', $r),
            ];
            $sources[] = [
                'module' => 'crm',
                'model' => \Modules\Crm\Models\Customer::class,
                'columns' => ['display_name', 'company_name', 'email', 'phone'],
                'kind' => __('Customer'),
                'scope' => 'organization_id',
                'title' => fn ($r) => $r->display_name,
                'snippet' => fn ($r) => $r->email ?: $r->phone ?: '',
                'route' => fn ($r) => route('crm.customers.edit', $r),
            ];
        }

        if (class_exists(\Modules\Invoicing\Models\Document::class)) {
            $sources[] = [
                'module' => 'invoicing',
                'model' => \Modules\Invoicing\Models\Document::class,
                'columns' => ['number', 'reference'],
                'kind' => __('Invoice'),
                'scope' => 'organization_id',
                'title' => fn ($r) => $r->number,
                'snippet' => fn ($r) => $r->formattedTotal() . ' · ' . $r->statusLabel(),
                'route' => fn ($r) => route('invoicing.invoices.edit', $r),
            ];
            $sources[] = [
                'module' => 'invoicing',
                'model' => \Modules\Invoicing\Models\Item::class,
                'columns' => ['name', 'sku'],
                'kind' => __('Item'),
                'scope' => 'organization_id',
                'title' => fn ($r) => $r->name,
                'snippet' => fn ($r) => $r->sku ?: '',
                'route' => fn ($r) => route('invoicing.items.edit', $r),
            ];
        }

        // The resource modules all follow the same records.edit shape, so they
        // are declared in a loop rather than a block each.
        foreach (self::resourceSources() as $spec) {
            if (class_exists($spec['model'])) {
                $sources[] = $spec;
            }
        }

        return $sources;
    }

    /** @return array<int,array> the generated resource-module sources */
    private static function resourceSources(): array
    {
        $defs = [
            ['assets', \Modules\Assets\Models\Asset::class, ['name', 'tag', 'serial_number', 'assigned_to'],
                __('Asset'), fn ($r) => $r->name, fn ($r) => $r->tag ?: ''],
            ['tickets', \Modules\Tickets\Models\Ticket::class, ['subject', 'requester', 'reference'],
                __('Ticket'), fn ($r) => $r->subject, fn ($r) => $r->requester ?: ''],
            ['cart', \Modules\Cart\Models\Order::class, ['number', 'customer_name'],
                __('Order'), fn ($r) => $r->number, fn ($r) => $r->customer_name ?: ''],
            ['workerly', \Modules\Workerly\Models\Shift::class, ['employee', 'activity', 'project'],
                __('Shift'), fn ($r) => $r->employee . ' — ' . $r->activity, fn ($r) => $r->worked_on?->toDateString() ?: ''],
            ['expenses', \Modules\Expenses\Models\Expense::class, ['account', 'vendor', 'reference'],
                __('Expense'), fn ($r) => $r->account, fn ($r) => $r->vendor ?: ''],
            ['inventory', \Modules\Inventory\Models\Stock::class, ['item_name', 'sku', 'location'],
                __('Stock'), fn ($r) => $r->item_name, fn ($r) => $r->location ?: ''],
            ['projects', \Modules\Projects\Models\Project::class, ['name', 'customer', 'code'],
                __('Project'), fn ($r) => $r->name, fn ($r) => $r->customer ?: ''],
            ['purchasing', \Modules\Purchasing\Models\PurchaseOrder::class, ['number', 'vendor', 'reference'],
                __('Purchase order'), fn ($r) => $r->number, fn ($r) => $r->vendor ?: ''],
        ];

        return array_map(fn ($d) => [
            'module' => $d[0],
            'model' => $d[1],
            'columns' => $d[2],
            'kind' => $d[3],
            'scope' => 'organization_id',
            'title' => $d[4],
            'snippet' => $d[5],
            'route' => fn ($r) => route($d[0] . '.records.edit', $r),
        ], $defs);
    }

    /**
     * The sources a given user may search, filtered by module access.
     *
     * A member who cannot open Expenses does not get expense rows in their
     * results — the search must not become a side channel around the module
     * gate.
     */
    public static function for(User $user): array
    {
        $organization = $user->organization;

        return array_values(array_filter(
            self::sources(),
            fn (array $source) => $organization
                && Modules::exists($source['module'])
                && $organization->allowsModule($user->orgRole(), $source['module']),
        ));
    }
}
