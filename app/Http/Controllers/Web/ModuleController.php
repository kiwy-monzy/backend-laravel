<?php

namespace App\Http\Controllers\Web;

use App\Models\Organization;
use App\Support\Access;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The base every module controller extends.
 *
 * The middleware has already established that this member may *enter* the
 * module. What is left is the per-action check — `$this->authorizeAction('delete')`
 * — and scoping queries to the organization, which is the one mistake that
 * leaks another tenant's records.
 */
abstract class ModuleController extends AdminController
{
    /** Set by each module's controllers; matches the manifest slug. */
    protected string $module = '';

    protected function organization(): ?Organization
    {
        return $this->me()->organization;
    }

    protected function organizationId(): ?string
    {
        return $this->me()->organization_id;
    }

    protected function role(): string
    {
        return $this->me()->orgRole();
    }

    /**
     * Refuse an action this member's role does not carry.
     *
     * Call it at the top of every write. The message names the role because
     * "Forbidden" sends people to the wrong person for help.
     */
    protected function authorizeAction(string $action): void
    {
        if (! Access::can($this->role(), $action, $this->employeeType(), $this->module)) {
            throw new AccessDeniedHttpException(sprintf(
                '%s cannot %s here.',
                Access::roleLabel($this->role()),
                Access::ACTION_LABELS[$action] ?? $action,
            ));
        }
    }

    /** True when the view should render a button at all. */
    protected function may(string $action): bool
    {
        return Access::can($this->role(), $action, $this->employeeType(), $this->module);
    }

    protected function employeeType(): ?string
    {
        return $this->me()->employeeType();
    }

    /**
     * Constrain a module query to this organization.
     *
     * Every module list must go through this. Unlike the website scoping in
     * AdminController, there is no owner-sees-everything escape hatch: an
     * organization's invoices are never another organization's business.
     */
    protected function scopedToOrg(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('organization_id', $this->organizationId());
    }
}
