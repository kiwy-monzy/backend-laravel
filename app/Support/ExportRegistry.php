<?php

namespace App\Support;

/**
 * What can be exported, and how each row becomes a spreadsheet line.
 *
 * Declarative like `SearchRegistry`, and gated the same way: a source belongs
 * to a module, so exporting it needs both module access *and* the `export`
 * action. Export is exactly the operation an organization most wants to
 * control — it is how data leaves — so it is a permission of its own, not
 * folded into "view".
 */
final class ExportRegistry
{
    /**
     * @return array<string,array{
     *   module:string,
     *   label:string,
     *   model:class-string,
     *   headers:array<int,string>,
     *   row:callable,
     *   with?:array<int,string>,
     *   order?:string,
     * }>
     */
    public static function sources(): array
    {
        $sources = [];

        if (class_exists(\Modules\Crm\Models\Customer::class)) {
            $sources['customers'] = [
                'module' => 'crm',
                'label' => __('Customers'),
                'model' => \Modules\Crm\Models\Customer::class,
                'headers' => ['Name', 'Company', 'Type', 'Email', 'Phone', 'Currency', 'Terms', 'Active', 'Created'],
                'row' => fn ($c) => [
                    $c->display_name, $c->company_name, $c->contact_type, $c->email, $c->phone,
                    $c->currency, $c->termLabel(), $c->active ? 'Yes' : 'No', $c->created_at?->toDateString(),
                ],
            ];
            $sources['leads'] = [
                'module' => 'crm',
                'label' => __('Leads'),
                'model' => \Modules\Crm\Models\Lead::class,
                'headers' => ['Name', 'Company', 'Email', 'Phone', 'Source', 'Status', 'Value', 'Follow up', 'Created'],
                'row' => fn ($l) => [
                    $l->name, $l->company, $l->email, $l->phone, $l->sourceLabel(), $l->statusLabel(),
                    (float) $l->value, $l->follow_up_on?->toDateString(), $l->created_at?->toDateString(),
                ],
            ];
        }

        if (class_exists(\Modules\Invoicing\Models\Document::class)) {
            $sources['invoices'] = [
                'module' => 'invoicing',
                'label' => __('Invoices'),
                'model' => \Modules\Invoicing\Models\Document::class,
                'with' => ['customer'],
                'order' => 'issue_date',
                'headers' => ['Number', 'Type', 'Customer', 'Issued', 'Due', 'Status', 'Total', 'Paid', 'Balance', 'Currency'],
                'row' => fn ($d) => [
                    $d->number, $d->doc_type, $d->customer?->display_name, $d->issue_date?->toDateString(),
                    $d->due_date?->toDateString(), $d->statusLabel(),
                    $d->total_minor / 100, $d->paid_minor / 100, $d->balanceMinor() / 100, $d->currency,
                ],
            ];
        }

        if (class_exists(\Modules\Contracts\Models\Contract::class)) {
            $sources['contracts'] = [
                'module' => 'contracts',
                'label' => __('Contracts'),
                'model' => \Modules\Contracts\Models\Contract::class,
                'with' => ['customer', 'activities'],
                'headers' => ['Reference', 'Title', 'Client', 'Type', 'Status', 'Value', 'Progress %', 'Starts', 'Ends', 'Manager', 'Site'],
                'row' => fn ($c) => [
                    $c->reference, $c->title, $c->clientLabel(), $c->typeLabel(), $c->statusLabel(),
                    $c->value_minor / 100, $c->progress(), $c->starts_on?->toDateString(),
                    $c->ends_on?->toDateString(), $c->manager, $c->site_location,
                ],
            ];
        }

        if (class_exists(\Modules\Workerly\Models\Shift::class)) {
            $sources['shifts'] = [
                'module' => 'workerly',
                'label' => __('Shifts'),
                'model' => \Modules\Workerly\Models\Shift::class,
                'order' => 'worked_on',
                'headers' => ['Employee', 'Type', 'Activity', 'Project/Contract', 'Date', 'Hours', 'Rate', 'Billable', 'Status'],
                'row' => fn ($s) => [
                    $s->employee, $s->employee_type, $s->activity, $s->project, $s->worked_on?->toDateString(),
                    (float) $s->hours, $s->rate_minor / 100, $s->billable ? 'Yes' : 'No', $s->status,
                ],
            ];
        }

        if (class_exists(\Modules\Assets\Models\Asset::class)) {
            $sources['assets'] = [
                'module' => 'assets',
                'label' => __('Assets'),
                'model' => \Modules\Assets\Models\Asset::class,
                'headers' => ['Tag', 'Asset', 'Category', 'Serial', 'Assigned to', 'Status', 'Purchased', 'Cost', 'Book value'],
                'row' => fn ($a) => [
                    $a->tag, $a->name, $a->category, $a->serial_number, $a->assigned_to, $a->status,
                    $a->purchased_on?->toDateString(), $a->purchase_cost_minor / 100, $a->current_value_minor / 100,
                ],
            ];
        }

        return $sources;
    }

    public static function find(string $key): ?array
    {
        return self::sources()[$key] ?? null;
    }
}
