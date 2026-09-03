<?php

namespace App\Support;

use App\Models\User;

/**
 * What the typeahead pickers can search.
 *
 * **A select element is a list you have already loaded.** With two and a half
 * thousand customers and two thousand items, every invoice form was shipping
 * four and a half thousand `<option>` tags to the browser before anyone typed a
 * character — slow to render, impossible to scan, and growing with the business.
 * These sources are queried a few rows at a time instead, as the user types.
 *
 * Each entry names the module it belongs to, so a member who cannot open CRM
 * cannot enumerate its customers through a lookup either; results are always
 * scoped to the caller's organization by the controller.
 */
final class LookupRegistry
{
    /**
     * @return array<string,array{module:string,model:class-string,columns:array<int,string>,label:callable,meta:callable,filter:?callable}>
     */
    public static function sources(): array
    {
        $s = [];

        if (class_exists(\Modules\Crm\Models\Customer::class)) {
            $s['customers'] = [
                'module' => 'crm',
                'model' => \Modules\Crm\Models\Customer::class,
                'columns' => ['display_name', 'company_name', 'email', 'phone'],
                'label' => fn ($r) => $r->display_name,
                'meta' => fn ($r) => $r->email ?: $r->phone ?: '',
                'filter' => fn ($q) => $q->where('active', true)->where('contact_type', 'customer'),
            ];

            $s['vendors'] = [
                'module' => 'crm',
                'model' => \Modules\Crm\Models\Customer::class,
                'columns' => ['display_name', 'company_name', 'email'],
                'label' => fn ($r) => $r->display_name,
                'meta' => fn ($r) => $r->email ?: '',
                'filter' => fn ($q) => $q->where('active', true)->where('contact_type', 'vendor'),
            ];
        }

        if (class_exists(\Modules\Invoicing\Models\Item::class)) {
            $s['items'] = [
                'module' => 'invoicing',
                'model' => \Modules\Invoicing\Models\Item::class,
                'columns' => ['name', 'sku'],
                'label' => fn ($r) => $r->name,
                // The rate is what the invoice line needs, so it travels with
                // the result and the form does not need a second request.
                'meta' => fn ($r) => $r->sku,
                'extra' => fn ($r) => ['rate' => \Modules\Invoicing\Models\Money::toDecimal($r->rate_minor), 'unit' => $r->unit],
                'filter' => fn ($q) => $q->where('active', true),
            ];
        }

        if (class_exists(\Modules\Procurement\Models\Vendor::class)) {
            $s['suppliers'] = [
                'module' => 'procurement',
                'model' => \Modules\Procurement\Models\Vendor::class,
                'columns' => ['name', 'code', 'email'],
                'label' => fn ($r) => $r->name,
                'meta' => fn ($r) => $r->code ?: '',
                'filter' => fn ($q) => $q->where('active', true),
            ];
        }

        if (class_exists(\Modules\Departments\Models\Department::class)) {
            $s['departments'] = [
                'module' => 'departments',
                'model' => \Modules\Departments\Models\Department::class,
                'columns' => ['name', 'code'],
                'label' => fn ($r) => $r->name,
                'meta' => fn ($r) => $r->code ?: '',
                'filter' => fn ($q) => $q->where('active', true),
            ];
        }

        if (class_exists(\Modules\ServiceHub\Models\Provider::class)) {
            $s['service_providers'] = [
                'module' => 'servicehub',
                'model' => \Modules\ServiceHub\Models\Provider::class,
                'columns' => ['name', 'code', 'phone', 'zone'],
                'label' => fn ($r) => $r->name,
                'meta' => fn ($r) => $r->zone ?: ($r->phone ?: ''),
                // Only providers we would actually send to a customer: a denied
                // applicant appearing in the picker is how one gets assigned.
                'filter' => fn ($q) => $q->where('status', 'approved')->where('active', true),
            ];
        }

        if (class_exists(\Modules\ServiceHub\Models\Service::class)) {
            $s['services'] = [
                'module' => 'servicehub',
                'model' => \Modules\ServiceHub\Models\Service::class,
                'columns' => ['name', 'category'],
                'label' => fn ($r) => $r->name,
                'meta' => fn ($r) => $r->category ?: '',
                'extra' => fn ($r) => [
                    'rate' => \Modules\Invoicing\Models\Money::toDecimal((int) $r->price_minor),
                    'duration_minutes' => (int) $r->duration_minutes,
                ],
                'filter' => fn ($q) => $q->where('active', true),
            ];
        }

        return $s;
    }

    public static function find(string $name): ?array
    {
        return self::sources()[$name] ?? null;
    }

    /** May this user look this source up at all? */
    public static function allows(?User $user, string $name): bool
    {
        $source = self::find($name);

        return $source && $user?->allowedModule($source['module']);
    }
}
