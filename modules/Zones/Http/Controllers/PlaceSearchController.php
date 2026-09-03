<?php

namespace Modules\Zones\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * "Where is Mikocheni?" — the search box above the drawing map.
 *
 * The system this replaces used Google Places, which needs a billed API key.
 * Nominatim answers the same question for nothing, at the cost of a usage
 * policy: identify yourself, and stay under a request a second.
 *
 * **Which is why this is a server proxy and not a fetch() in the page.** A
 * keystroke handler cannot promise a rate, cannot set a User-Agent that the
 * browser will honour, and would put the endpoint in reach of anyone with the
 * page open. Here the answers are cached, so the second person to look up the
 * same place costs nothing at all.
 */
class PlaceSearchController extends ModuleController
{
    protected string $module = 'zones';

    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 3) {
            return response()->json(['places' => []]);
        }

        $config = config('zones.geocoder');
        $key = 'zones:geocode:' . md5(mb_strtolower($term) . '|' . $config['country_codes']);

        $places = Cache::remember($key, now()->addMinutes($config['cache_minutes']), function () use ($term, $config) {
            try {
                $response = Http::withHeaders(['User-Agent' => $config['user_agent']])
                    ->timeout(8)
                    ->get($config['endpoint'], [
                        'q' => $term,
                        'format' => 'jsonv2',
                        'limit' => 8,
                        'addressdetails' => 0,
                        'countrycodes' => $config['country_codes'],
                    ]);

                if (! $response->successful()) {
                    return null;
                }

                return collect($response->json())
                    ->map(fn ($place) => [
                        'name' => $place['display_name'] ?? '',
                        'lat' => (float) ($place['lat'] ?? 0),
                        'lng' => (float) ($place['lon'] ?? 0),
                        // The viewport, so picking a result frames the town
                        // rather than dropping the map on its centimetre.
                        'bounds' => isset($place['boundingbox']) ? [
                            'min_lat' => (float) $place['boundingbox'][0],
                            'max_lat' => (float) $place['boundingbox'][1],
                            'min_lng' => (float) $place['boundingbox'][2],
                            'max_lng' => (float) $place['boundingbox'][3],
                        ] : null,
                    ])
                    ->filter(fn ($place) => $place['lat'] || $place['lng'])
                    ->values()
                    ->all();
            } catch (\Throwable $e) {
                Log::warning('Zone place search failed: ' . $e->getMessage());

                return null;
            }
        });

        // A failed lookup is not cached as "no results" — the next search would
        // inherit the outage for a day. Cache::remember stores null, so clear
        // it and answer honestly instead.
        if ($places === null) {
            Cache::forget($key);

            return response()->json([
                'places' => [],
                'error' => __('Place search is unavailable right now. You can still draw the zone by hand.'),
            ], 200);
        }

        return response()->json(['places' => $places]);
    }
}
