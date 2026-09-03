<?php

namespace Modules\Workerly\Http\Controllers;

use App\Http\Controllers\Web\ResourceModuleController;
use App\Support\Field;
use Modules\Contracts\Models\Contract;
use Modules\Invoicing\Models\Money;
use Modules\Workerly\Models\Shift;

class ShiftController extends ResourceModuleController
{
    protected string $module = 'workerly';

    protected string $model = Shift::class;

    protected string $title = 'Shift';

    protected string $orderBy = 'worked_on';

    protected string $orderDirection = 'desc';

    protected array $searchable = ['employee', 'activity', 'project'];

    protected function routeBase(): string
    {
        return 'workerly.records';
    }

    protected function fields(): array
    {
        return [
            Field::text('employee', __('Employee'))->required(),
            Field::select('employee_type', __('Employee type'), Shift::TYPES, 'labourer'),
            // Tying a shift to a contract is what makes its hours count toward
            // that contract's progress and its billable labour — the Contracts
            // module reads shifts back by `contract_id`. Left blank, the shift
            // is ad-hoc work recorded under the free-text project name below.
            Field::select('contract_id', __('Contract'), $this->contractOptions()),
            Field::text('project', __('Project (if not a contract)')),
            Field::text('activity', __('Activity'))->required(),
            Field::date('worked_on', __('Date'))->required(),
            Field::number('hours', __('Hours'), 0, 0.25)->required(),
            Field::select('status', __('Status'), Shift::STATUSES, 'logged'),
            Field::money('rate', __('Hourly rate')),
            Field::checkbox('billable', __('Billable to the contract'), true),
            Field::textarea('notes', __('Notes')),
        ];
    }

    /**
     * The org's contracts as a picker, newest first, blank option leading.
     *
     * Only contracts that are actually open to labour (not draft/terminated)
     * are offered — you should not be logging hours against a contract that
     * has not started or has been called off.
     */
    protected function contractOptions(): array
    {
        if (! class_exists(Contract::class)) {
            return ['' => __('— none —')];
        }

        $contracts = Contract::query()
            ->where('organization_id', $this->organizationId())
            ->whereIn('status', ['active', 'on_hold', 'completed'])
            ->orderByDesc('starts_on')
            ->get(['id', 'reference', 'title']);

        $options = ['' => __('— none —')];

        foreach ($contracts as $c) {
            $label = $c->reference ? "{$c->reference} · {$c->title}" : $c->title;
            $options[$c->id] = $label;
        }

        return $options;
    }

    protected function columns(): array
    {
        return [
            'employee' => __('Employee'),
            'employee_type' => __('Type'),
            'activity' => __('Activity'),
            'worked_on' => __('Date'),
            'hours' => __('Hours'),
            'status' => __('Status'),
        ];
    }

    /**
     * The form works in major units; the column stores minor.
     *
     * Converting here rather than in the model keeps the single rounding step
     * where the string from the browser is first turned into a number.
     */
    protected function validated(\Illuminate\Http\Request $request): array
    {
        $data = parent::validated($request);

        $data['rate_minor'] = Money::toMinor($data['rate'] ?? 0);
            unset($data['rate']);

        return $data;
    }
}
