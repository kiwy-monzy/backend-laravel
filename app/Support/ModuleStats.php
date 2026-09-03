<?php

namespace App\Support;

use App\Models\User;

/**
 * How much is in each module, for the dashboard.
 *
 * The dashboard only ever reported the website — pages, gallery images,
 * messages — while the organization's actual work sat in twenty modules it
 * never mentioned. Someone with a hundred thousand records saw four zeros and
 * concluded the system had lost them.
 *
 * One declaration per counted thing, the same shape as {@see SearchRegistry}:
 * the module it belongs to (so it is skipped when the member cannot open it),
 * the model, and where it goes. Counting is a `COUNT(*)` per entry against an
 * indexed organization column — cheap enough to do on every dashboard load,
 * and never a row read into memory.
 */
final class ModuleStats
{
    /**
     * @return array<int,array{module:string,model:class-string,label:string,route:?string,filter:?callable}>
     */
    public static function sources(): array
    {
        $s = [];

        $add = function (string $module, string $model, string $label, ?string $route = null, ?callable $filter = null) use (&$s) {
            if (class_exists($model)) {
                $s[] = compact('module', 'model', 'label', 'route', 'filter');
            }
        };

        $add('crm', \Modules\Crm\Models\Customer::class, 'Customers', 'crm.customers.index',
            fn ($q) => $q->where('contact_type', 'customer'));
        $add('crm', \Modules\Crm\Models\Lead::class, 'Leads', 'crm.leads.index');

        $add('invoicing', \Modules\Invoicing\Models\Document::class, 'Invoices', 'invoicing.invoices.index',
            fn ($q) => $q->where('doc_type', 'invoice'));
        $add('invoicing', \Modules\Invoicing\Models\Item::class, 'Items', 'invoicing.items.index');
        $add('invoicing', \Modules\Invoicing\Models\Payment::class, 'Payments', 'invoicing.payments.index');

        $add('inventory', \Modules\Inventory\Models\Stock::class, 'Stock lines', 'inventory.records.index');
        $add('assets', \Modules\Assets\Models\Asset::class, 'Assets', 'assets.records.index');
        $add('procurement', \Modules\Procurement\Models\Vendor::class, 'Vendors');
        $add('expenses', \Modules\Expenses\Models\Expense::class, 'Expenses', 'expenses.records.index');
        $add('accounting', \Modules\Accounting\Models\JournalEntry::class, 'Journal entries', 'accounting.journal.index');
        $add('contracts', \Modules\Contracts\Models\Contract::class, 'Contracts', 'contracts.contracts.index');
        $add('departments', \Modules\Departments\Models\Department::class, 'Departments', 'departments.records.index');
        $add('projects', \Modules\Projects\Models\Project::class, 'Projects', 'projects.records.index');
        $add('workerly', \Modules\Workerly\Models\Shift::class, 'Shifts', 'workerly.records.index');
        $add('tickets', \Modules\Tickets\Models\Ticket::class, 'Tickets', 'tickets.records.index');
        $add('cart', \Modules\Cart\Models\Order::class, 'Orders', 'cart.records.index');
        $add('bookings', \Modules\Bookings\Models\Appointment::class, 'Bookings', 'bookings.records.index');
        $add('billing', \Modules\Billing\Models\Subscription::class, 'Subscriptions', 'billing.records.index');

        $add('zones', \Modules\Zones\Models\Zone::class, 'Zones', 'zones.records.index');

        $add('servicehub', \Modules\ServiceHub\Models\Provider::class, 'Service providers', 'servicehub.providers.index');
        $add('servicehub', \Modules\ServiceHub\Models\ServiceRequest::class, 'Service requests', 'servicehub.requests.index');
        $add('servicehub', \Modules\ServiceHub\Models\Booking::class, 'Service bookings', 'servicehub.bookings.index');

        return $s;
    }

    /**
     * Counts for every module this user may actually open, grouped by module.
     *
     * Returns [] for a user with no organization rather than throwing: the
     * dashboard renders for people who are still being set up.
     *
     * @return array<string,array{label:string,icon:string,rows:array<int,array{label:string,count:int,route:?string}>}>
     */
    public static function for(?User $user): array
    {
        $organization = $user?->organization;

        if (! $organization) {
            return [];
        }

        $out = [];

        foreach (self::sources() as $source) {
            if (! $user->allowedModule($source['module'])) {
                continue;
            }

            try {
                $query = $source['model']::query()->where('organization_id', $organization->id);

                if ($source['filter']) {
                    $query = ($source['filter'])($query);
                }

                $count = $query->count();
            } catch (\Throwable $e) {
                // A module whose table has not been migrated yet must not take
                // the whole dashboard down with it.
                continue;
            }

            $slug = $source['module'];

            $out[$slug] ??= [
                'label' => Modules::label($slug),
                'icon' => Modules::find($slug)['icon'] ?? 'module',
                'rows' => [],
            ];

            $out[$slug]['rows'][] = [
                'label' => __($source['label']),
                'count' => $count,
                'route' => $source['route'] && \Illuminate\Support\Facades\Route::has($source['route'])
                    ? route($source['route'])
                    : null,
            ];
        }

        return $out;
    }
}
