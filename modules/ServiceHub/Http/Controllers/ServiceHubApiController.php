<?php

namespace Modules\ServiceHub\Http\Controllers;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\ServiceHub\Models\Booking;
use Modules\ServiceHub\Models\Provider;
use Modules\ServiceHub\Models\Service;
use Modules\ServiceHub\Models\ServiceRequest;

/**
 * Read access for the customer and provider apps.
 *
 * Every query is filtered by the caller's organization, taken from the token
 * rather than from anything in the request — a client that could name its own
 * organization could read every other one's providers.
 */
class ServiceHubApiController extends ApiController
{
    public function providers(Request $request): JsonResponse
    {
        return $this->collection($request, Provider::query()->bookable()->orderBy('name'));
    }

    public function services(Request $request): JsonResponse
    {
        $query = Service::query()->where('active', true)->orderBy('name');

        if ($provider = $request->query('provider_id')) {
            $query->where('provider_id', $provider);
        }

        return $this->collection($request, $query);
    }

    public function requests(Request $request): JsonResponse
    {
        $query = ServiceRequest::query()->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $this->collection($request, $query);
    }

    public function bookings(Request $request): JsonResponse
    {
        $query = Booking::query()->orderByDesc('scheduled_at');

        foreach (['status', 'provider_id'] as $filter) {
            if ($value = $request->query($filter)) {
                $query->where($filter, $value);
            }
        }

        return $this->collection($request, $query);
    }

    public function booking(Request $request, string $record): JsonResponse
    {
        $row = Booking::where('organization_id', $this->organizationId($request))->find($record);

        return $row ? $this->json($row->toApi()) : $this->fail('Not found', 404);
    }

    private function collection(Request $request, $query): JsonResponse
    {
        $records = $query
            ->where('organization_id', $this->organizationId($request))
            ->limit(min((int) $request->query('limit', 100), 500))
            ->get()
            ->map(fn ($r) => $r->toApi())
            ->values();

        return $this->json(['records' => $records]);
    }
}
