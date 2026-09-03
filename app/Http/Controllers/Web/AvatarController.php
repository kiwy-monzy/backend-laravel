<?php

namespace App\Http\Controllers\Web;

use App\Models\OrganizationMember;
use App\Services\MediaLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Portraits for people — the signed-in user's own, and the team's.
 *
 * Both land in the organization's `avatars` collection, so a picture follows
 * the same tenancy rule as every other file: it lives under
 * `uploads/{organization}/avatars/`, never in a shared folder. The system
 * administrator has an organization of its own (Knowlia), so its files are
 * stored the same way rather than being a special case.
 */
class AvatarController extends AdminController
{
    public function __construct(private MediaLibrary $media)
    {
    }

    /** The signed-in user's own portrait. */
    public function updateMine(Request $request): RedirectResponse
    {
        $user = $this->me();
        $url = $this->resolve($request, $user->organization_id);

        if ($url === null) {
            return back()->with('error', __('Choose a file to upload, or pick one from storage.'));
        }

        $user->forceFill(['profile_image' => $url])->save();

        return back()->with('status', __('Portrait updated.'));
    }

    /** A team member's portrait, which is also what the public site shows. */
    public function updateMember(Request $request, OrganizationMember $member): RedirectResponse
    {
        $this->assertMayManageTeam();

        abort_unless($member->organization_id === $this->me()->organization_id, 404);

        $url = $this->resolve($request, $member->organization_id);

        if ($url === null) {
            return back()->with('error', __('Choose a file to upload, or pick one from storage.'));
        }

        $member->update(['photo_url' => $url]);

        return back()->with('status', __('Portrait updated for :name.', ['name' => $member->displayName()]));
    }

    /**
     * The portrait, however it arrived: uploaded, or chosen from storage.
     *
     * Both routes exist because both are natural — a new photograph is
     * uploaded, but the picture of somebody already on file should not have to
     * be uploaded a second time to be used again.
     *
     * A picked path is checked against the organization's own uploads before it
     * is trusted: the field is a text input, and a text input is a place
     * somebody can type another tenant's path.
     */
    private function resolve(Request $request, ?string $organizationId): ?string
    {
        if ($request->hasFile('avatar')) {
            $request->validate(['avatar' => ['required', 'image', 'max:4096']]);

            $stored = $this->media->storeUpload($request->file('avatar'), $organizationId, 'avatars');
            $this->media->register($stored, $organizationId, $this->collection());

            return $stored['url'];
        }

        $path = trim((string) $request->input('photo_url', ''));

        if ($path === '') {
            return null;
        }

        $owned = \App\Models\Upload::where('organization_id', $organizationId)
            ->where('url', $path)
            ->exists();

        abort_unless($owned, 403, 'That file does not belong to this organization.');

        return $path;
    }

    public function removeMember(OrganizationMember $member): RedirectResponse
    {
        $this->assertMayManageTeam();

        abort_unless($member->organization_id === $this->me()->organization_id, 404);

        // The file stays in storage: it may be in use elsewhere, and the
        // Storage module is where deleting files belongs.
        $member->update(['photo_url' => null]);

        return back()->with('status', __('Portrait removed.'));
    }

    /**
     * The organization's avatars collection, created on first use.
     *
     * Selectable, so a portrait already on file can be chosen again from the
     * picker instead of being uploaded a second time. The picker groups by
     * collection, so portraits stay distinguishable from the site's
     * photographs without being hidden from it.
     */
    private function collection(): ?\App\Models\StorageCollection
    {
        $organizationId = $this->me()->organization_id;

        if (! $organizationId) {
            return null;
        }

        return \App\Models\StorageCollection::firstOrCreate(
            ['organization_id' => $organizationId, 'slug' => 'avatars'],
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'name' => 'Avatars',
                'description' => 'Portraits of people on the team.',
                'min_role' => 'employee',
                'is_system' => true,
                'selectable' => true,
            ],
        );
    }

    private function assertMayManageTeam(): void
    {
        if (! $this->me()->isOwner() && $this->me()->orgRole() !== 'admin') {
            throw new AccessDeniedHttpException('Only an owner or organization admin can change team portraits.');
        }
    }
}
