<?php

namespace App\Http\Controllers\Web;

use App\Models\ModuleAccess;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Support\Access;
use App\Support\ModuleNav;
use App\Support\Modules;
use App\Support\Templates;
use App\Support\ThemeFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The organization itself: its profile, its people, who may enter which
 * module, and its subscription.
 *
 * These four pages are core rather than a module, because they are what the
 * modules gate on — a module that could revoke its own gate would not be a
 * gate.
 */
class OrganizationController extends AdminController
{
    public function edit()
    {
        $org = $this->org();

        return view('organization.edit', [
            'organization' => $org,
            'canManage' => $this->canManage(),
        ]);
    }

    /**
     * Move the current user into another organization they belong to.
     *
     * This is how the system administrator moves between the tenants it runs —
     * Knowlia and the agencies (TANROADS, TARURA, MoW-Buildings) it set up. The
     * guard is membership: you may only switch into an organization you hold an
     * active seat on, so this grants no access the seat did not already carry.
     */
    public function switch(Request $request): RedirectResponse
    {
        $user = $this->me();
        $target = (string) $request->input('organization_id');

        $isMember = OrganizationMember::where('organization_id', $target)
            ->where('user_id', $user->id)
            ->where('active', true)
            ->exists();

        if (! $isMember) {
            throw new AccessDeniedHttpException('You are not a member of that organization.');
        }

        $user->forceFill(['organization_id' => $target])->save();

        return redirect()->route('dashboard')->with('status', __('Switched organization.'));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->assertCanManage();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:190'],
            'country' => ['nullable', 'string', 'max:8'],
            'currency' => ['nullable', 'string', 'max:8'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'site_name' => ['nullable', 'string', 'max:190'],
            'site_title' => ['nullable', 'string', 'max:190'],
            'logo_text' => ['nullable', 'string', 'max:190'],
            'social_links' => ['nullable', 'array'],
            'social_links.*' => ['nullable', 'string', 'max:500'],
            'visibility' => ['nullable', 'array'],
            'visibility.*' => ['nullable', 'boolean'],
            'template' => ['nullable', 'in:'.implode(',', array_keys(Templates::ALL))],
            'theme' => ['nullable', 'in:'.implode(',', array_keys(ThemeFactory::PRESETS))],
            'override_primary' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'override_secondary' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'override_tertiary' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
        ]);

        $org = $this->org();

        $overrides = array_filter([
            'primary' => $data['override_primary'] ?? null,
            'secondary' => $data['override_secondary'] ?? null,
            'tertiary' => $data['override_tertiary'] ?? null,
        ]);

        // The organization columns stay the module-facing copy (invoices, mail,
        // quotes); the public site reads the same values from the profile's
        // `general` block, so a save writes both sides.
        $org->update($data + [
            'general' => $this->mergeGeneral($org->general ?? [], $request),
            'template' => $data['template'] ?? null,
            'theme' => $data['theme'] ?? null,
            'theme_overrides' => $overrides ?: null,
        ]);

        return back()->with('status', __('Organization saved.'));
    }

    /**
     * Fold the profile form's site-facing fields into `organizations.general`.
     *
     * Fields the form does not post (or that were never given a field — the
     * legacy `theme_color`) survive untouched, and unchecked visibility
     * toggles are written as false explicitly.
     */
    private function mergeGeneral(array $existing, Request $request): array
    {
        $general = $existing;

        foreach (['site_name', 'site_title', 'logo_text'] as $key) {
            $general[$key] = (string) $request->input($key, $general[$key] ?? '');
        }

        // Contact and logo come from the same inputs as the organization
        // columns, so the two can never drift again.
        $general['contact_email'] = (string) $request->input('email', $general['contact_email'] ?? '');
        $general['contact_phone'] = (string) $request->input('phone', $general['contact_phone'] ?? '');
        $general['address'] = (string) $request->input('address', $general['address'] ?? '');
        $general['logo_url'] = (string) $request->input('logo_url', $general['logo_url'] ?? '');

        foreach (['facebook', 'twitter', 'instagram', 'linkedin'] as $network) {
            $general['social_links'][$network] = (string) $request->input(
                "social_links.$network",
                $general['social_links'][$network] ?? ''
            );
        }

        foreach ([
            'hero', 'about', 'projects', 'services', 'achievements',
            'team', 'gallery', 'volunteer', 'donate', 'footer',
        ] as $key) {
            $general['visibility'][$key] = $request->boolean(
                "visibility.$key",
                $general['visibility'][$key] ?? true
            );
        }

        return $general;
    }

    /** The team: everyone with a seat, and the role they hold. */
    public function team()
    {
        $org = $this->org();

        return view('organization.team', [
            'organization' => $org,
            'members' => $org->members()->with('user')->get()
                ->sortBy(fn ($m) => [Access::rank($m->role), $m->user?->username]),
            'unseated' => User::where('organization_id', $org->id)
                ->whereNotIn('id', $org->members()->pluck('user_id'))
                ->get(),
            'canManage' => $this->canManage(),
        ]);
    }

    /**
     * Add a person to the organization, creating their login too.
     *
     * This is the "admin adds users as team to its organization" path: one
     * form, because making someone create an account and *then* seat them is
     * two steps where an admin only ever wants one.
     */
    public function addMember(Request $request): RedirectResponse
    {
        $this->assertCanManage();

        $org = $this->org();

        $data = $request->validate([
            'username' => ['required', 'string', 'max:60', 'unique:users,username'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:'.implode(',', Access::ROLES)],
            'employee_type' => ['nullable', 'in:'.implode(',', array_keys(Access::EMPLOYEE_TYPES))],
            'job_title' => ['nullable', 'string', 'max:120'],
            'website_id' => ['nullable', 'string'],
        ]);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => Hash::make($data['password']),
            // Team members administer modules, not the public website. Anyone
            // who also needs to edit the site gets promoted on the Users page,
            // deliberately as a separate decision.
            'role' => 'user',
            'active' => true,
            'organization_id' => $org->id,
            'website_id' => $data['website_id'] ?: $org->websites()->value('id'),
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => $data['role'],
            'employee_type' => $data['employee_type'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'active' => true,
        ]);

        return back()->with('status', __('Added :name to the team.', ['name' => $user->username]));
    }

    public function updateMember(Request $request, OrganizationMember $member): RedirectResponse
    {
        $this->assertCanManage();
        $this->assertSameOrg($member->organization_id);

        $data = $request->validate([
            'role' => ['required', 'in:'.implode(',', Access::ROLES)],
            'employee_type' => ['nullable', 'in:'.implode(',', array_keys(Access::EMPLOYEE_TYPES))],
            'job_title' => ['nullable', 'string', 'max:120'],
            'active' => ['nullable', 'boolean'],
        ]);

        // Demoting yourself out of admin locks you out of this very page, and
        // the only way back is another admin or the database.
        if ($member->user_id === $this->me()->id && $data['role'] !== $member->role) {
            return back()->with('error', __('You cannot change your own role.'));
        }

        $member->update([
            'role' => $data['role'],
            'employee_type' => $data['employee_type'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'active' => (bool) ($data['active'] ?? false),
        ]);

        return back()->with('status', __('Member updated.'));
    }

    public function removeMember(OrganizationMember $member): RedirectResponse
    {
        $this->assertCanManage();
        $this->assertSameOrg($member->organization_id);

        if ($member->user_id === $this->me()->id) {
            return back()->with('error', __('You cannot remove your own seat.'));
        }

        $member->delete();

        return back()->with('status', __('Member removed from the team.'));
    }

    /** The matrix: which roles may enter which modules, for this organization. */
    public function access()
    {
        $org = $this->org();

        $matrix = [];
        $sections = [];
        $sectionMatrix = [];

        foreach (Modules::slugs() as $module) {
            $sections[$module] = ModuleNav::grantable($module);
        }

        foreach (Access::ROLES as $role) {
            foreach (Modules::slugs() as $module) {
                $matrix[$role][$module] = $org->allowsModule($role, $module);

                foreach (array_keys($sections[$module]) as $key) {
                    $sectionMatrix[$role][$module][$key] = $org->allowsSection($role, $module, $key);
                }
            }
        }

        return view('organization.access', [
            'organization' => $org,
            'modules' => Modules::enabled(),
            'matrix' => $matrix,
            // What each module can hand out a tab at a time, and who holds what.
            'sections' => $sections,
            'sectionMatrix' => $sectionMatrix,
            'canManage' => $this->canManage(),
        ]);
    }

    public function updateAccess(Request $request): RedirectResponse
    {
        $this->assertCanManage();

        $org = $this->org();
        $submitted = $request->input('access', []);

        foreach (Access::ROLES as $role) {
            foreach (Modules::slugs() as $module) {
                // An admin always keeps every module. Letting the matrix lock
                // admins out would make the organization unadministrable, and
                // there is no second door.
                $allowed = $role === 'admin'
                    ? true
                    : (bool) ($submitted[$role][$module] ?? false);

                ModuleAccess::updateOrCreate(
                    ['organization_id' => $org->id, 'role' => $role, 'module' => $module, 'section' => null],
                    ['allowed' => $allowed],
                );

                // Then the tabs. A section row is only written when it differs
                // from the module it sits in — storing every inherited value
                // would freeze today's defaults into the database.
                foreach (array_keys(ModuleNav::grantable($module)) as $key) {
                    $wanted = $role === 'admin'
                        ? true
                        : (bool) ($request->input("sections.$role.$module.".str_replace('.', '__', $key)) ?? $allowed);

                    if ($wanted === $allowed) {
                        ModuleAccess::where('organization_id', $org->id)
                            ->where('role', $role)
                            ->where('module', $module)
                            ->where('section', $key)
                            ->delete();

                        continue;
                    }

                    ModuleAccess::updateOrCreate(
                        ['organization_id' => $org->id, 'role' => $role, 'module' => $module, 'section' => $key],
                        ['allowed' => $wanted],
                    );
                }
            }
        }

        return back()->with('status', __('Module access updated.'));
    }

    public function subscription()
    {
        $org = $this->org();

        return view('organization.subscription', [
            'organization' => $org,
            'plans' => Organization::PLANS,
            'modules' => Modules::enabled(),
            'canManage' => $this->canManage(),
        ]);
    }

    public function updateSubscription(Request $request): RedirectResponse
    {
        $this->assertCanManage();

        $data = $request->validate([
            'plan' => ['required', 'in:'.implode(',', array_keys(Organization::PLANS))],
        ]);

        $org = $this->org();

        // No payment is taken here: this records the plan an organization is
        // on. Wiring a processor is a separate piece of work, and pretending
        // otherwise would be the kind of fake checkout nobody should ship.
        $org->update([
            'plan' => $data['plan'],
            'subscription_status' => $data['plan'] === 'free_trial' ? 'trialing' : 'active',
            'renews_at' => $data['plan'] === 'free_trial' ? null : now()->addMonth(),
        ]);

        return back()->with('status', __('Plan changed to :plan.', ['plan' => $org->planLabel()]));
    }

    private function org(): Organization
    {
        $org = $this->me()->organization;

        if (! $org) {
            throw new AccessDeniedHttpException('You do not belong to an organization yet.');
        }

        return $org;
    }

    private function canManage(): bool
    {
        return $this->me()->canInModule('manage_users');
    }

    private function assertCanManage(): void
    {
        if (! $this->canManage()) {
            throw new AccessDeniedHttpException('Only an organization administrator can change this.');
        }
    }

    private function assertSameOrg(?string $organizationId): void
    {
        if ($organizationId !== $this->me()->organization_id) {
            throw new AccessDeniedHttpException('That record belongs to another organization.');
        }
    }
}
