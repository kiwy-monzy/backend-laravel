<?php

namespace App\Http\Controllers\Web;

use App\Models\Website;
use App\Support\SearchRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The titlebar's global search, across every module the member may open.
 *
 * **Every source comes from `SearchRegistry`, and every one is scoped and
 * gated.** Scoped to the member's organization, and gated by module access —
 * so a search cannot surface a record from a module the member has no way to
 * reach through the nav. The search is a shortcut, never a side channel.
 */
class SearchController extends AdminController
{
    /** Rows to take from each source before moving to the next. */
    private const PER_SOURCE = 4;

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        $organizationId = $this->organizationId();
        $hits = [];

        foreach (SearchRegistry::for($this->me()) as $source) {
            $rows = $source['model']::query()
                ->where($source['scope'], $organizationId)
                ->where(function ($query) use ($source, $like) {
                    foreach ($source['columns'] as $column) {
                        $query->orWhere($column, 'like', $like);
                    }
                })
                ->limit(self::PER_SOURCE)
                ->get();

            foreach ($rows as $row) {
                $hits[] = [
                    'kind' => $source['kind'],
                    'title' => ($source['title'])($row),
                    'snippet' => ($source['snippet'])($row),
                    'href' => ($source['route'])($row),
                ];
            }
        }

        // An owner also searches their own websites — those are not a module.
        if ($this->me()->isOwner()) {
            foreach (Website::where('organization_id', $organizationId)
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('slug', 'like', $like))
                ->limit(self::PER_SOURCE)->get() as $site) {
                $hits[] = [
                    'kind' => __('Website'),
                    'title' => $site->name,
                    'snippet' => $site->domain ?: '/s/' . $site->slug,
                    'href' => route('website.sites.show', $site),
                ];
            }
        }

        // A system admin's search reaches every user; an owner's, their org's.
        if ($this->me()->isOwner()) {
            foreach ($this->me()->visibleUsers()
                ->where(fn ($u) => $u->where('username', 'like', $like)->orWhere('email', 'like', $like))
                ->limit(self::PER_SOURCE)->get() as $user) {
                $hits[] = [
                    'kind' => __('User'),
                    'title' => $user->username,
                    'snippet' => Str::limit($user->email, 40),
                    'href' => route('users.edit', $user),
                ];
            }
        }

        return response()->json($hits);
    }
}
