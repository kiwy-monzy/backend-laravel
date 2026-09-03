<?php

namespace App\Http\Controllers\Web;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OrganizationModule;
use App\Models\User;
use App\Models\Website;
use App\Support\Access;
use App\Support\Modules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The installation, seen from above: every organization, every user.
 *
 * Only a system admin reaches any of it. This is where organizations are
 * created, owners assigned, and modules granted — the decisions an
 * organization must not be able to make for itself.
 */
class SystemController extends AdminController
{
    public function index()
    {
        $this->assertSystemAdmin();

        return view('system.index', [
            'organizations' => Organization::withCount(['members', 'websites'])->orderBy('name')->get(),
            'counts' => [
                'organizations' => Organization::count(),
                'users' => User::count(),
                'system_admins' => User::where('role', 'system_admin')->count(),
                'owners' => User::where('role', 'owner')->count(),
                'websites' => Website::count(),
                'modules' => count(Modules::enabled()),
            ],
        ]);
    }

    /** Every user on the installation, whatever organization they belong to. */
    public function users(Request $request)
    {
        $this->assertSystemAdmin();

        $q = $request->query('q');
        $role = $request->query('role');

        return view('system.users', [
            'users' => User::query()
                ->when($q, fn ($query) => $query->where(fn ($w) => $w
                    ->where('username', 'like', "%$q%")
                    ->orWhere('email', 'like', "%$q%")))
                ->when($role, fn ($query) => $query->where('role', $role))
                ->with(['organization', 'membershipRelation'])
                ->orderByRaw("CASE role WHEN 'system_admin' THEN 0 WHEN 'owner' THEN 1 ELSE 2 END")
                ->orderBy('username')
                ->paginate(40)
                ->withQueryString(),
            'organizations' => Organization::orderBy('name')->get(),
            'q' => $q,
            'role' => $role,
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $this->assertSystemAdmin();

        $data = $request->validate([
            'username' => ['required', 'string', 'max:60', 'unique:users,username'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:' . implode(',', User::ROLES)],
            'organization_id' => ['nullable', 'string'],
            'org_role' => ['nullable', 'in:' . implode(',', Access::ROLES)],
        ]);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'role' => $data['role'],
            'active' => true,
            'organization_id' => $data['organization_id'] ?: null,
            'website_id' => $data['organization_id']
                ? Website::where('organization_id', $data['organization_id'])->value('id')
                : null,
        ]);

        if ($data['organization_id']) {
            OrganizationMember::updateOrCreate(
                ['organization_id' => $data['organization_id'], 'user_id' => $user->id],
                ['role' => $data['org_role'] ?? ($data['role'] === 'owner' ? 'admin' : 'employee'), 'active' => true],
            );
        }

        return back()->with('status', __('Created :name.', ['name' => $user->username]));
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $this->assertSystemAdmin();

        $data = $request->validate([
            'role' => ['required', 'in:' . implode(',', User::ROLES)],
            'organization_id' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        // The last system admin must not be able to demote themselves: there
        // would then be nobody who could put it back.
        if ($user->id === $this->me()->id
            && $data['role'] !== 'system_admin'
            && User::where('role', 'system_admin')->count() <= 1) {
            return back()->with('error', __('You are the only system admin. Promote someone else first.'));
        }

        $user->update([
            'role' => $data['role'],
            'organization_id' => $data['organization_id'] ?: null,
            'active' => (bool) ($data['active'] ?? false),
        ]);

        return back()->with('status', __('Updated :name.', ['name' => $user->username]));
    }

    public function destroyUser(User $user): RedirectResponse
    {
        $this->assertSystemAdmin();

        if ($user->id === $this->me()->id) {
            return back()->with('error', __('You cannot delete your own account.'));
        }

        OrganizationMember::where('user_id', $user->id)->delete();
        $user->delete();

        return back()->with('status', __('User deleted.'));
    }

    public function createOrganization()
    {
        $this->assertSystemAdmin();

        return view('system.organization-form', [
            'organization' => new Organization(['plan' => 'free_trial', 'currency' => 'TZS', 'country' => 'TZ']),
            'owners' => User::whereIn('role', ['owner', 'system_admin'])->orderBy('username')->get(),
            'plans' => Organization::PLANS,
        ]);
    }

    public function storeOrganization(Request $request): RedirectResponse
    {
        $this->assertSystemAdmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', 'unique:organizations,slug'],
            'owner_id' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'plan' => ['required', 'in:' . implode(',', array_keys(Organization::PLANS))],
            'currency' => ['nullable', 'string', 'max:8'],
            'country' => ['nullable', 'string', 'max:8'],
        ]);

        $organization = Organization::create($data + [
            'id' => (string) Str::uuid(),
            'subscription_status' => 'trialing',
            'trial_ends_at' => now()->addDays(Organization::TRIAL_DAYS),
        ]);

        // A new organization starts with every module its plan covers, so it
        // is usable the moment it exists rather than after a second visit.
        foreach (Modules::slugs() as $module) {
            OrganizationModule::create([
                'organization_id' => $organization->id,
                'module' => $module,
                'granted' => $organization->planIncludes($module),
                'granted_by' => $this->me()->id,
            ]);
        }

        if ($data['owner_id'] ?? null) {
            $owner = User::find($data['owner_id']);
            $owner?->update(['organization_id' => $organization->id]);
            if ($owner) {
                OrganizationMember::updateOrCreate(
                    ['organization_id' => $organization->id, 'user_id' => $owner->id],
                    ['role' => 'admin', 'active' => true],
                );
            }
        }

        return redirect()->route('system.organization', $organization)
            ->with('status', __('Organization created.'));
    }

    /** One organization: its owner, its team, and the modules it may use. */
    public function organization(Organization $organization)
    {
        $this->assertSystemAdmin();

        $granted = $organization->moduleGrants()->pluck('granted', 'module')->all();

        return view('system.organization', [
            'organization' => $organization->loadCount(['members', 'websites']),
            'modules' => Modules::enabled(),
            'granted' => $granted,
            'owners' => User::whereIn('role', ['owner', 'system_admin'])->orderBy('username')->get(),
            'members' => $organization->members()->with('user')->get(),
            'plans' => Organization::PLANS,
        ]);
    }

    public function updateOrganization(Request $request, Organization $organization): RedirectResponse
    {
        $this->assertSystemAdmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'owner_id' => ['nullable', 'string'],
            'plan' => ['required', 'in:' . implode(',', array_keys(Organization::PLANS))],
            'subscription_status' => ['required', 'in:trialing,active,past_due,cancelled'],
            'trial_days' => ['nullable', 'integer', 'between:0,365'],
        ]);

        $organization->update([
            'name' => $data['name'],
            'owner_id' => $data['owner_id'] ?: null,
            'plan' => $data['plan'],
            'subscription_status' => $data['subscription_status'],
            'trial_ends_at' => isset($data['trial_days'])
                ? now()->addDays((int) $data['trial_days'])
                : $organization->trial_ends_at,
        ]);

        // Assigning an owner also seats them, otherwise they own an
        // organization they cannot open.
        if ($data['owner_id'] ?? null) {
            $owner = User::find($data['owner_id']);
            if ($owner) {
                $owner->update(['organization_id' => $organization->id]);
                OrganizationMember::updateOrCreate(
                    ['organization_id' => $organization->id, 'user_id' => $owner->id],
                    ['role' => 'admin', 'active' => true],
                );
            }
        }

        return back()->with('status', __('Organization saved.'));
    }

    /** Grant or revoke the modules this organization may use at all. */
    public function updateModules(Request $request, Organization $organization): RedirectResponse
    {
        $this->assertSystemAdmin();

        $submitted = $request->input('modules', []);

        foreach (Modules::slugs() as $module) {
            OrganizationModule::updateOrCreate(
                ['organization_id' => $organization->id, 'module' => $module],
                ['granted' => (bool) ($submitted[$module] ?? false), 'granted_by' => $this->me()->id],
            );
        }

        return back()->with('status', __('Module grants updated for :org.', ['org' => $organization->name]));
    }

    private function assertSystemAdmin(): void
    {
        if (! $this->me()->isSystemAdmin()) {
            throw new AccessDeniedHttpException('Only a system administrator can reach this.');
        }
    }
}
