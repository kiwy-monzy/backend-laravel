<?php

namespace App\Http\Controllers\Web;

use App\Support\LookupRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Typeahead lookups for the form pickers.
 *
 * One endpoint for every picker, driven by {@see LookupRegistry}. It returns at
 * most a screenful of rows, always scoped to the caller's organization, and
 * refuses a source whose module the caller cannot open — a picker must not
 * become a way to enumerate records the rest of the admin would hide.
 */
class LookupController extends AdminController
{
    public function __invoke(Request $request, string $source): JsonResponse
    {
        $user = $this->me();

        abort_unless(LookupRegistry::allows($user, $source), 404);

        $spec = LookupRegistry::find($source);
        $term = trim((string) $request->query('q', ''));

        $query = $spec['model']::query()->where('organization_id', $user->organization_id);

        if ($spec['filter']) {
            $query = ($spec['filter'])($query);
        }

        if ($term !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
            $query->where(function ($q) use ($spec, $like) {
                foreach ($spec['columns'] as $column) {
                    $q->orWhere($column, 'like', $like);
                }
            });
        }

        // A specific id is asked for when a form loads with a value already set
        // and needs its label — otherwise an edit screen would show a blank box
        // over a perfectly good foreign key.
        if ($id = $request->query('id')) {
            $query = $spec['model']::query()
                ->where('organization_id', $user->organization_id)
                ->whereKey($id);
        }

        $rows = $query->limit(20)->get()->map(function ($r) use ($spec) {
            $row = [
                'id' => $r->getKey(),
                'label' => ($spec['label'])($r),
                'meta' => ($spec['meta'])($r),
            ];

            if (isset($spec['extra'])) {
                $row += ($spec['extra'])($r);
            }

            return $row;
        });

        return response()->json(['results' => $rows]);
    }
}
