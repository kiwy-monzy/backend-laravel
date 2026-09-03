<?php

namespace Modules\Zones\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Illuminate\Support\Facades\DB;
use Modules\Zones\Models\Zone;

class ZonesController extends ModuleController
{
    protected string $module = 'zones';

    public function index()
    {
        $zones = Zone::where('organization_id', $this->organizationId())->orderBy('name')->get();

        return view('zones::overview', [
            'organization' => $this->organization(),
            'zones' => $zones,
            'drawn' => $zones->filter->isDrawn()->count(),
            'inactive' => $zones->where('active', false)->count(),
            'covered' => round($zones->filter->isDrawn()->sum->approximateAreaKm2(), 1),

            // What is actually zoned, by kind, so the page says whether the
            // areas are being used rather than merely existing.
            'byType' => DB::table('zonables')
                ->join('zones', 'zones.id', '=', 'zonables.zone_id')
                ->where('zones.organization_id', $this->organizationId())
                ->select('zonable_type', 'role', DB::raw('count(*) as total'))
                ->groupBy('zonable_type', 'role')
                ->get()
                ->map(fn ($row) => [
                    'label' => class_basename($row->zonable_type),
                    'role' => $row->role,
                    'total' => (int) $row->total,
                ]),

            // Built here rather than in the view: a blade that assembles its
            // own payload is a blade that has to be debugged as code.
            'shapes' => $zones->filter->isDrawn()->map(fn (Zone $zone) => [
                'name' => $zone->name,
                'colour' => $zone->colour,
                'ring' => $zone->ring(),
                'active' => $zone->active,
            ])->values(),

            'centre' => config('zones.default_centre'),
            'zoom' => config('zones.default_zoom'),
        ]);
    }
}
