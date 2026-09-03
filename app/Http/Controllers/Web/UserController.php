<?php

namespace App\Http\Controllers\Web;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\Website;
use App\Support\Access;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Accounts, seen from wherever the viewer stands.
 *
 * **Three tiers, three answers to "who can I see".** A system admin sees every
 * account on the installation. An owner sees their own organization's. Anybody
 * else sees nobody — the route rejects them outright rather than showing an
 * empty table, because an empty table looks like a bug.
 *
 * The one rule that makes the rest safe: **only a system admin can mint a
 * system admin or an owner.** Without it the role field is a
 * privilege-escalation button and every other check here is decoration.
 */
class UserController extends AdminController
{
    public function index(Request $request)
    {
        $this->assertCanSeeUsers();

        $me = $this->me();
        $q = $request->query('q');

        return view('users.index', [
            'users' => $me->visibleUsers()
                ->when($q, fn ($query) => $query->where(fn ($w) => $w
                    ->where('username', 'like', "%$q%")
                    ->orWhere('email', 'like', "%$q%")))
                ->with(['website', 'organization'])
                ->orderByRaw("CASE role WHEN 'system_admin' THEN 0 WHEN 'owner' THEN 1 ELSE 2 END")
                ->orderBy('username')
                ->get(),
            'q' => $q,
            'isSystemAdmin' => $me->isSystemAdmin(),
        ]);
    }

    public function create()
    {
        $this->assertCanSeeUsers();

        return view('users.form', [
            'user' => new User([
                'role' => 'member',
                'active' => true,
                'website_id' => $this->siteId(),
                'organization_id' => $this->me()->organization_id,
            ]),
            'websites' => $this->assignableWebsites(),
            'organizations' => $this->assignableOrganizations(),
            'roles' => $this->me()->assignableRoles(),
            'orgRoles' => Access::ROLES,
            'member' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assertCanSeeUsers();

        $data = $request->validate([
            'username' => ['required', 'string', 'max:60', 'unique:users,username'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:' . implode(',', $this->me()->assignableRoles())],
            'org_role' => ['required', 'in:' . implode(',', Access::ROLES)],
            'organization_id' => ['nullable', 'string'],
            'website_id' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        $organizationId = $this->resolveOrganization($data['organization_id'] ?? null);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'role' => $data['role'],
            'organization_id' => $organizationId,
            'website_id' => $data['website_id'] ?: Website::where('organization_id', $organizationId)->value('id'),
            'active' => (bool) ($data['active'] ?? true),
        ]);

        OrganizationMember::updateOrCreate(
            ['organization_id' => $organizationId, 'user_id' => $user->id],
            ['role' => $data['org_role'], 'active' => true],
        );

        return redirect()->route('users.index')->with('status', __('User created.'));
    }

    public function edit(User $user)
    {
        $this->assertCanTouch($user);

        return view('users.form', [
            'user' => $user,
            'websites' => $this->assignableWebsites(),
            'organizations' => $this->assignableOrganizations(),
            'roles' => $this->me()->assignableRoles(),
            'orgRoles' => Access::ROLES,
            'member' => $user->membership(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->assertCanTouch($user);

        $data = $request->validate([
            'username' => ['required', 'string', 'max:60', 'unique:users,username,' . $user->id . ',id'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'in:' . implode(',', $this->me()->assignableRoles())],
            'org_role' => ['required', 'in:' . implode(',', Access::ROLES)],
            'organization_id' => ['nullable', 'string'],
            'website_id' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        if ($user->id === $this->me()->id && $data['role'] !== $user->role) {
            return back()->with('error', __('You cannot change your own role.'));
        }

        $organizationId = $this->resolveOrganization($data['organization_id'] ?? null);

        $user->fill([
            'username' => $data['username'],
            'email' => $data['email'],
            'role' => $data['role'],
            'organization_id' => $organizationId,
            'website_id' => $data['website_id'] ?: $user->website_id,
            'active' => (bool) ($data['active'] ?? false),
        ]);

        if (! empty($data['password'])) {
            $user->password_hash = Hash::make($data['password']);
        }

        $user->save();

        OrganizationMember::updateOrCreate(
            ['organization_id' => $organizationId, 'user_id' => $user->id],
            ['role' => $data['org_role']],
        );

        return redirect()->route('users.index')->with('status', __('User saved.'));
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->assertCanTouch($user);

        if ($user->id === $this->me()->id) {
            return back()->with('error', __('You cannot delete your own account.'));
        }

        if ($user->isSystemAdmin() && ! $this->me()->isSystemAdmin()) {
            throw new AccessDeniedHttpException('Only a system admin can remove a system admin.');
        }

        OrganizationMember::where('user_id', $user->id)->delete();
        $user->delete();

        return back()->with('status', __('User deleted.'));
    }

    /** Only system admins and organization owners have a Users page at all. */
    private function assertCanSeeUsers(): void
    {
        if (! $this->me()->isOwner()) {
            throw new AccessDeniedHttpException(
                'Only a system admin or an organization owner can manage accounts. '
                . 'To manage your colleagues, use Organization → Team.'
            );
        }
    }

    private function assertCanTouch(User $user): void
    {
        $this->assertCanSeeUsers();

        $me = $this->me();

        if ($me->isSystemAdmin()) {
            return;
        }

        // An owner is confined to their own organization, and cannot touch a
        // system admin who happens to be seated in it.
        if ($user->organization_id !== $me->organization_id || $user->isSystemAdmin()) {
            throw new AccessDeniedHttpException('That account is outside your organization.');
        }
    }

    /** An owner may only ever file people into their own organization. */
    private function resolveOrganization(?string $requested): ?string
    {
        return $this->me()->isSystemAdmin()
            ? ($requested ?: $this->me()->organization_id)
            : $this->me()->organization_id;
    }

    private function assignableWebsites()
    {
        return $this->me()->websites()->orderBy('name')->get();
    }

    private function assignableOrganizations()
    {
        return $this->me()->isSystemAdmin()
            ? Organization::orderBy('name')->get()
            : Organization::whereKey($this->me()->organization_id)->get();
    }
}
