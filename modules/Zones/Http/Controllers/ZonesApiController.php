<?php

namespace Modules\Zones\Http\Controllers;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Zones\Models\Zone;

/**
 * Zones for the customer and provider apps.
 *
 * `resolve` is the one that matters: an app knows where its user is standing
 * and needs to know which area that falls in before it can show them anything
 * — which providers travel there, whether the organization trades there at all.
 */
class ZonesApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $zones = Zone::where('organization_id', $this->organizationId($request))
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (Zone $zone) => $zone->toApi())
            ->values();

        return $this->json(['zones' => $zones]);
    }

    public function show(Request $request, string $zone): JsonResponse
    {
        $row = Zone::where('organization_id', $this->organizationId($request))->find($zone);

        return $row ? $this->json($row->toApi()) : $this->fail('Not found', 404);
    }

    /**
     * Which zone a point falls in.
     *
     * A point outside every zone is a 200 with a null zone, not a 404: "we do
     * not cover where you are" is a real answer the app has to show, and an
     * error status makes it look like the request was wrong.
     */
    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $zone = Zone::locate(
            $this->organizationId($request),
            (float) $data['lat'],
            (float) $data['lng'],
        );

        return $this->json([
            'covered' => (bool) $zone,
            'zone' => $zone?->toApi(),
        ]);
    }
}
