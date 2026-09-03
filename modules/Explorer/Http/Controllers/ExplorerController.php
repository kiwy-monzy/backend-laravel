<?php

namespace Modules\Explorer\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * The map explorer.
 *
 * The network map is a real slippy map — OpenStreetMap underneath, the
 * TANROADS trunk and regional corridors drawn over it — rather than the bare
 * canvas it used to be. Roads without a basemap were lines in space: you could
 * see the shape of the network but not the town a corridor runs through, which
 * is most of what anyone opens this page to find out.
 *
 * The network itself (1,879 links, 1,639 junctions, from the official TANROADS
 * GIS) is a static JSON asset, fetched once by the page and cached by the
 * browser. It is reference data that changes when TANROADS republishes, not
 * per-organization data, so it stays out of the database.
 */
class ExplorerController extends ModuleController
{
    protected string $module = 'explorer';

    /** Where the extracted network lives, under the public disk. */
    private const NETWORK_ASSET = 'vendor/map/tanroads.json';

    public function index()
    {
        return view('explorer::index', [
            'organization' => $this->organization(),
            // Path-only: an absolute URL would carry APP_URL's host, which is
            // not necessarily the host the browser actually asked on.
            'networkUrl' => route('explorer.network', [], false),
            'stats' => $this->stats(),
        ]);
    }

    /**
     * The road network, as JSON.
     *
     * Served through the module rather than linked as a bare asset so it sits
     * behind the same module gate as the page that draws it, and so the page
     * has one URL to fetch whether the file is bundled or not.
     */
    public function network(): JsonResponse
    {
        $path = public_path(self::NETWORK_ASSET);

        if (! File::exists($path)) {
            return response()->json(['trunk' => [], 'regional' => [], 'nodes' => [], 'corridors' => [], 'stats' => []]);
        }

        // Streamed as a file rather than decoded and re-encoded: it is a
        // megabyte of coordinates and the app has no reason to parse any of it.
        return response()->json(
            json_decode(File::get($path), true) ?: []
        )->setEncodingOptions(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** The headline counts, read from the asset so the page and the data agree. */
    private function stats(): array
    {
        $path = public_path(self::NETWORK_ASSET);

        if (! File::exists($path)) {
            return [];
        }

        $data = json_decode(File::get($path), true);

        return is_array($data) ? ($data['stats'] ?? []) : [];
    }

    public function terrain()
    {
        // Prefer the copy in this organization's storage (the system admin's
        // Knowlia has it seeded); fall back to the bundled asset otherwise.
        $stored = 'uploads/' . $this->organizationId() . '/map/dem_dar.png';
        $demUrl = Storage::disk('public')->exists($stored)
            ? Storage::disk('public')->url($stored)
            : asset('vendor/map/dem_dar.png');

        return view('explorer::terrain', [
            'organization' => $this->organization(),
            'demUrl' => $demUrl,
            'inStorage' => Storage::disk('public')->exists($stored),
        ]);
    }
}
