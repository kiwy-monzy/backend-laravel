<?php

namespace Modules\Zones\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use App\Support\Access;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Zones\Models\Zone;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Attach zones to a record of any zoned kind.
 *
 * One endpoint instead of a zones control bolted onto each module's form.
 * `{kind}` is resolved through the whitelist in `config/zones.php` — it is a
 * string from the browser, so it names an entry in a list we wrote, never a
 * class.
 *
 * **The permission checked is the target module's, not this one's.** Being
 * allowed to draw a map is not the same as being allowed to change where a
 * shipment is going, and checking `module:zones` here would have made it so.
 */
class ZoneAttachController extends ModuleController
{
    protected string $module = 'zones';

    public function update(Request $request, string $kind, string $id): RedirectResponse
    {
        $definition = config('zones.zonable.' . $kind);

        if (! $definition || ! class_exists($definition['model'])) {
            throw new NotFoundHttpException('Nothing of that kind can be zoned.');
        }

        $user = $this->me();

        if (! $user->allowedModule($definition['module'])) {
            throw new AccessDeniedHttpException(
                'Your role does not have access to ' . \App\Support\Modules::label($definition['module']) . '.'
            );
        }

        if (! Access::can($this->role(), 'edit', $this->employeeType(), $definition['module'])) {
            throw new AccessDeniedHttpException(sprintf(
                '%s cannot change zones here.',
                Access::roleLabel($this->role()),
            ));
        }

        $record = $this->findInOrganization($definition['model'], $id);

        // Only this organization's zones, whatever ids were posted: the form is
        // a list of checkboxes, and a list of checkboxes is a list of ids the
        // browser may rewrite.
        $zoneIds = Zone::where('organization_id', $this->organizationId())
            ->whereIn('id', (array) $request->input('zones', []))
            ->pluck('id')
            ->all();

        $record->syncZones($zoneIds);

        return back()->with('status', __('Zones updated.'));
    }

    /**
     * Find the record, refusing another organization's.
     *
     * The organization itself is the one kind whose key *is* the organization,
     * so it is matched on its own id rather than on a column it does not have.
     */
    private function findInOrganization(string $model, string $id)
    {
        $query = $model::query();

        $record = $model === \App\Models\Organization::class
            ? $query->where('id', $this->organizationId())->find($id)
            : $query->where('organization_id', $this->organizationId())->find($id);

        if (! $record) {
            throw new NotFoundHttpException('No such record.');
        }

        return $record;
    }
}
