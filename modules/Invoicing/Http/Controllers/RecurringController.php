<?php

namespace Modules\Invoicing\Http\Controllers;

use App\Http\Controllers\Web\ResourceModuleController;
use App\Support\Field;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Crm\Models\Customer;
use Modules\Invoicing\Models\Document;
use Modules\Invoicing\Models\Money;
use Modules\Invoicing\Models\RecurringProfile;

class RecurringController extends ResourceModuleController
{
    protected string $module = 'invoicing';

    protected string $model = RecurringProfile::class;

    protected string $title = 'Recurring invoice';

    protected string $orderBy = 'next_run_on';

    protected string $orderDirection = 'asc';

    protected array $searchable = ['title', 'notes'];

    protected function routeBase(): string
    {
        return 'invoicing.recurring';
    }

    protected function fields(): array
    {
        return [
            Field::text('title', __('Description'))->required(),
            Field::select('customer_id', __('Customer'), $this->customerOptions()),
            Field::select('interval', __('Every'), RecurringProfile::INTERVALS, 'monthly'),
            Field::date('next_run_on', __('Next issue date'))->required(),
            Field::date('ends_on', __('Ends on')),
            Field::money('amount', __('Amount')),
            Field::select('status', __('Status'), RecurringProfile::STATUSES, 'active'),
            Field::textarea('notes', __('Notes')),
        ];
    }

    protected function columns(): array
    {
        return [
            'title' => __('Description'),
            'interval' => __('Every'),
            'next_run_on' => __('Next issue'),
            'status' => __('Status'),
            'issued_count' => __('Issued'),
        ];
    }

    /** The form works in major units; the column stores minor. */
    protected function validated(\Illuminate\Http\Request $request): array
    {
        $data = parent::validated($request);

        $data['amount_minor'] = Money::toMinor($data['amount'] ?? 0);
        unset($data['amount']);

        $data['currency'] ??= $this->organization()?->currency ?? 'TZS';

        return $data;
    }

    /**
     * Raise this cycle's invoice and move the profile on.
     *
     * The document is an ordinary draft invoice — nothing downstream needs to
     * know it came from a recurrence — and `next_run_on` advances by exactly
     * one interval, so issuing late does not shift the whole schedule.
     */
    public function issue(string $id): RedirectResponse
    {
        $this->authorizeAction('add');

        /** @var RecurringProfile $profile */
        $profile = $this->findScoped($id);

        $document = DB::transaction(function () use ($profile) {
            $document = Document::create([
                'id' => (string) Str::uuid(),
                'organization_id' => $this->organizationId(),
                'doc_type' => 'invoice',
                'number' => Document::nextNumber($this->organizationId(), 'invoice'),
                'status' => 'draft',
                'customer_id' => $profile->customer_id,
                'issue_date' => now(),
                'due_date' => now()->addDays(30),
                'currency' => $profile->currency,
                'subtotal_minor' => $profile->amount_minor,
                'total_minor' => $profile->amount_minor,
                'reference' => $profile->title,
                'notes' => $profile->notes,
            ]);

            $profile->update([
                'next_run_on' => $profile->advance(),
                'issued_count' => $profile->issued_count + 1,
            ]);

            return $document;
        });

        return redirect()
            ->route('invoicing.invoices.edit', $document)
            ->with('status', __('Raised :number.', ['number' => $document->number]));
    }

    private function customerOptions(): array
    {
        $options = ['' => __('— none —')];

        foreach ($this->scopedToOrg(Customer::query())->orderBy('display_name')->get() as $c) {
            $options[$c->id] = $c->display_name;
        }

        return $options;
    }
}
