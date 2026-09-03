<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Shared scoping for every admin page.
 *
 * **One website is in play at a time.** An admin has exactly one; an owner
 * picks from the titlebar and the choice lives in the session. Every list and
 * every write funnels through `scoped()` / `guard()` here rather than each
 * controller remembering to filter, because the failure mode of forgetting is
 * showing one charity another charity's donors.
 */
abstract class AdminController extends Controller
{
    use ValidatesRequests;

    protected function me(): User
    {
        return auth()->user();
    }

    /** The website currently being edited. */
    protected function site(): ?Website
    {
        $user = $this->me();

        if ($user->isOwner()) {
            $chosen = session('chrome.website_id');
            if ($chosen && $site = Website::find($chosen)) {
                return $site;
            }
        }

        return $user->website ?? Website::orderBy('created_at')->first();
    }

    protected function siteId(): ?string
    {
        return $this->site()?->id;
    }

    /**
     * The organization files and records are filed under.
     *
     * Needed here as well as on ModuleController because the website pages —
     * content, gallery — write into organization storage while still being
     * scoped to a *site*.
     */
    protected function organizationId(): ?string
    {
        return $this->me()->organization_id;
    }

    /**
     * Named `currentOrganization`, not `organization`.
     *
     * `SystemController` has a route action called `organization()` — a page
     * showing one — and a protected helper of the same name on the parent is a
     * signature clash that fatals the whole app at autoload. Controllers own
     * their action names; base helpers work around them.
     */
    protected function currentOrganization(): ?\App\Models\Organization
    {
        return $this->me()->organization;
    }

    /**
     * Constrain a query to the active website.
     *
     * An owner sees across sites deliberately — they are the person who has to
     * answer "how many donations came in today" for the whole estate — so the
     * filter only narrows when they have actively picked a site.
     */
    protected function scoped(Builder $query): Builder
    {
        $user = $this->me();

        if ($user->isOwner()) {
            $chosen = session('chrome.website_id');

            return $chosen ? $query->where('website_id', $chosen) : $query;
        }

        return $query->where('website_id', $user->website_id);
    }

    /** Reject a write against a row belonging to someone else's website. */
    protected function guard(?string $websiteId): void
    {
        if (! $this->me()->ownsWebsite($websiteId)) {
            throw new AccessDeniedHttpException('That record belongs to another website.');
        }
    }
}
