<?php

namespace Modules\ServiceHub\Http\Controllers;

use App\Http\Controllers\Web\ResourceModuleController;
use Modules\ServiceHub\Models\Provider;
use Modules\ServiceHub\Models\Service;

/**
 * The bits three of the four Service Hub lists need.
 *
 * Requests and bookings both have to offer "which provider" and "which
 * service", and both must offer only this organization's — a picker that
 * enumerates another tenant's suppliers is a data leak wearing a dropdown.
 * Written once here so neither controller can forget the scoping.
 */
abstract class ServiceHubResourceController extends ResourceModuleController
{
    protected string $module = 'servicehub';

    /**
     * Providers this organization may assign work to, as `id => name`.
     *
     * A blank first entry is deliberate: an unassigned request is the normal
     * state of a new one, and a select with no empty option forces the first
     * provider in the list onto every record that was never assigned.
     */
    protected function providerOptions(bool $bookableOnly = true): array
    {
        $query = Provider::query()->where('organization_id', $this->organizationId());

        if ($bookableOnly) {
            $query->bookable();
        }

        return ['' => __('— Unassigned —')] + $query->orderBy('name')->pluck('name', 'id')->all();
    }

    /** This organization's active catalogue, as `id => name`. */
    protected function serviceOptions(): array
    {
        return ['' => __('— None —')] + Service::query()
            ->where('organization_id', $this->organizationId())
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
