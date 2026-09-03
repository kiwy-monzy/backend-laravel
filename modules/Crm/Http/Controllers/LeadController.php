<?php

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Crm\Models\Lead;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The pipeline: enquiries that have not become customers yet.
 *
 * Website contact forms arrive here directly, which is the point — the person
 * who follows up sales should not have to watch a separate inbox to find out
 * somebody asked about the work.
 */
class LeadController extends ModuleController
{
    protected string $module = 'crm';

    public function index(Request $request)
    {
        $status = $request->query('status');

        $base = fn () => $this->scopedToOrg(Lead::query());

        return view('crm::leads.index', [
            'leads' => $base()
                ->search($request->query('q'))
                ->when($status === 'open', fn ($q) => $q->whereIn('status', Lead::OPEN_STATUSES))
                ->when($status && $status !== 'open', fn ($q) => $q->where('status', $status))
                ->when($request->query('source'), fn ($q, $s) => $q->where('source', $s))
                ->with(['customer', 'owner', 'website'])
                ->orderByDesc('created_at')
                ->paginate(30)
                ->withQueryString(),
            'q' => $request->query('q'),
            'status' => $status,
            'source' => $request->query('source'),
            'counts' => [
                'open' => $base()->whereIn('status', Lead::OPEN_STATUSES)->count(),
                'won' => $base()->where('status', 'won')->count(),
                'overdue' => $base()
                    ->whereIn('status', Lead::OPEN_STATUSES)
                    ->whereNotNull('follow_up_on')
                    ->whereDate('follow_up_on', '<', now())
                    ->count(),
            ],
            'owners' => $this->teamMembers(),
            'organization' => $this->organization(),
            'mayAdd' => $this->may('add'),
            'mayEdit' => $this->may('edit'),
            'mayDelete' => $this->may('delete'),
        ]);
    }

    public function create()
    {
        $this->authorizeAction('add');

        return view('crm::leads.form', [
            'lead' => new Lead(['source' => 'phone', 'status' => 'new']),
            'owners' => $this->teamMembers(),
            'organization' => $this->organization(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAction('add');

        $lead = Lead::create($this->validated($request) + [
            'id' => (string) Str::uuid(),
            'organization_id' => $this->organizationId(),
        ]);

        return redirect()->route('crm.leads.edit', $lead)->with('status', __('Lead created.'));
    }

    public function edit(string $lead)
    {
        return view('crm::leads.form', [
            'lead' => $this->find($lead),
            'owners' => $this->teamMembers(),
            'organization' => $this->organization(),
        ]);
    }

    public function update(Request $request, string $lead): RedirectResponse
    {
        $this->authorizeAction('edit');

        $this->find($lead)->update($this->validated($request));

        return back()->with('status', __('Lead saved.'));
    }

    /** Turn an enquiry into a customer, keeping the trail back to it. */
    public function convert(string $lead): RedirectResponse
    {
        $this->authorizeAction('edit');

        $found = $this->find($lead);
        $customer = $found->convert();

        return redirect()
            ->route('crm.customers.edit', $customer)
            ->with('status', __(':name is now a customer.', ['name' => $customer->display_name]));
    }

    public function destroy(string $lead): RedirectResponse
    {
        $this->authorizeAction('delete');

        $this->find($lead)->delete();

        return redirect()->route('crm.leads.index')->with('status', __('Lead deleted.'));
    }

    private function find(string $id): Lead
    {
        $lead = $this->scopedToOrg(Lead::query())->find($id);

        if (! $lead) {
            throw new NotFoundHttpException('No such lead.');
        }

        return $lead;
    }

    /** Who a lead can be assigned to: this organization's seated team. */
    private function teamMembers()
    {
        return User::whereIn(
            'id',
            OrganizationMember::where('organization_id', $this->organizationId())
                ->where('active', true)
                ->pluck('user_id'),
        )->orderBy('username')->get();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'company' => ['nullable', 'string', 'max:190'],
            'subject' => ['nullable', 'string', 'max:190'],
            'message' => ['nullable', 'string', 'max:5000'],
            'source' => ['required', 'in:' . implode(',', array_keys(Lead::SOURCES))],
            'status' => ['required', 'in:' . implode(',', array_keys(Lead::STATUSES))],
            'owner_id' => ['nullable', 'string'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'follow_up_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        return $data + [
            'email' => null, 'phone' => null, 'company' => null, 'subject' => null,
            'message' => null, 'owner_id' => null, 'value' => 0,
            'follow_up_on' => null, 'notes' => null,
        ];
    }
}
