<?php

namespace Modules\Zones\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Zones\Models\Zone;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Zone list, and the map form that draws one.
 *
 * Written out rather than extending ResourceModuleController: that base turns a
 * field list into a form, and the only field here that matters is a polygon
 * somebody drew. There is no `Field::polygon()` worth inventing for one screen.
 */
class ZoneController extends ModuleController
{
    protected string $module = 'zones';

    public function index(Request $request)
    {
        $query = Zone::query()->where('organization_id', $this->organizationId());

        if ($term = $request->query('q')) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like));
        }

        $zones = $query->orderBy('name')->paginate(30)->withQueryString();

        return view('zones::index', [
            'zones' => $zones,
            'q' => $request->query('q'),
            'organization' => $this->organization(),
            'mayAdd' => $this->may('add'),
            'mayDelete' => $this->may('delete'),
            'counts' => $this->attachmentCounts($zones->pluck('id')->all()),
        ]);
    }

    public function create()
    {
        $this->authorizeAction('add');

        return view('zones::form', $this->formData(new Zone([
            'colour' => '#2f6f4e',
            'active' => true,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAction('add');

        $zone = new Zone($this->validated($request) + [
            'id' => (string) Str::uuid(),
            'organization_id' => $this->organizationId(),
            'code' => \App\Support\Sequences::next($this->organizationId(), 'zone'),
        ]);

        $zone->save();

        return redirect()
            ->route('zones.records.edit', $zone)
            ->with('status', __('Zone created.'));
    }

    public function edit(string $id)
    {
        return view('zones::form', $this->formData($this->findScoped($id)));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAction('edit');

        $this->findScoped($id)->update($this->validated($request));

        return back()->with('status', __('Zone saved.'));
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorizeAction('delete');

        $zone = $this->findScoped($id);

        // The pairs go with it: a zonables row pointing at a zone that no
        // longer exists would keep a provider "covering" a deleted area.
        \Illuminate\Support\Facades\DB::table('zonables')->where('zone_id', $zone->id)->delete();
        $zone->delete();

        return redirect()
            ->route('zones.records.index')
            ->with('status', __('Zone deleted.'));
    }

    /**
     * Every other zone, so the map can draw them behind the one being edited.
     *
     * Drawing blind is how you end up with two areas overlapping by a street
     * and a provider that answers for both.
     */
    public function neighbours(Request $request, ?string $id = null)
    {
        $zones = Zone::query()
            ->where('organization_id', $this->organizationId())
            ->active()
            ->when($id, fn ($q) => $q->where('id', '!=', $id))
            ->get()
            ->filter->isDrawn()
            ->map(fn (Zone $zone) => [
                'id' => $zone->id,
                'name' => $zone->name,
                'colour' => $zone->colour,
                'coordinates' => $zone->ring(),
            ])
            ->values();

        return response()->json(['zones' => $zones]);
    }

    private function formData(Zone $zone): array
    {
        return [
            'zone' => $zone,
            'organization' => $this->organization(),
            'centre' => $zone->centre_lat !== null
                ? ['lat' => $zone->centre_lat, 'lng' => $zone->centre_lng]
                : config('zones.default_centre'),
            'zoom' => $zone->centre_lat !== null ? 13 : config('zones.default_zoom'),
            'neighboursUrl' => route('zones.records.neighbours', ['id' => $zone->id ?: 'new']),
            'searchUrl' => route('zones.places.search'),
            'mayEdit' => $this->may($zone->exists ? 'edit' : 'add'),
        ];
    }

    /**
     * Validate the form, including the ring itself.
     *
     * The polygon arrives as JSON from the map, so it is checked here rather
     * than by a rule: "an array of at least three [lat, lng] pairs, each a real
     * coordinate" is not something a validation string says clearly.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'description' => 'nullable|string|max:2000',
            'colour' => 'nullable|string|max:9',
            'coordinates' => 'required|string',
        ]);

        $ring = json_decode($data['coordinates'], true);

        if (! is_array($ring) || count($ring) < 3) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'coordinates' => __('Draw the zone on the map — it needs at least three corners.'),
            ]);
        }

        $clean = [];

        foreach ($ring as $point) {
            $lat = (float) ($point['lat'] ?? $point[0] ?? null);
            $lng = (float) ($point['lng'] ?? $point[1] ?? null);

            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'coordinates' => __('That shape has a corner outside the world.'),
                ]);
            }

            $clean[] = [round($lat, 7), round($lng, 7)];
        }

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'colour' => $data['colour'] ?: '#2f6f4e',
            'coordinates' => $clean,
            'active' => $request->boolean('active'),
        ];
    }

    /**
     * How many records each zone holds, by kind.
     *
     * One grouped query over the pivot rather than a count per zone per type:
     * the list would otherwise be a page of N×M queries the moment an
     * organization has a few dozen areas.
     */
    private function attachmentCounts(array $zoneIds): array
    {
        if (! $zoneIds) {
            return [];
        }

        $rows = \Illuminate\Support\Facades\DB::table('zonables')
            ->select('zone_id', 'zonable_type', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->whereIn('zone_id', $zoneIds)
            ->groupBy('zone_id', 'zonable_type')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[$row->zone_id][class_basename($row->zonable_type)] = (int) $row->total;
        }

        return $counts;
    }

    private function findScoped(string $id): Zone
    {
        $zone = Zone::where('organization_id', $this->organizationId())->find($id);

        if (! $zone) {
            throw new NotFoundHttpException('No such zone.');
        }

        return $zone;
    }
}
