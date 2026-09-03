<?php

namespace Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tickets\Models\Ticket;

class TicketsApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user') ?? $request->user();

        return $this->json([
            'records' => Ticket::where('organization_id', $user?->organization_id)
                ->orderByDesc('created_at')
                ->limit(min((int) $request->query('limit', 100), 500))
                ->get()
                ->map(fn (Ticket $r) => $r->toApi())
                ->values(),
        ]);
    }

    public function show(Request $request, string $record): JsonResponse
    {
        $user = $request->attributes->get('auth_user') ?? $request->user();

        $row = Ticket::where('organization_id', $user?->organization_id)->find($record);

        return $row ? $this->json($row->toApi()) : $this->fail('Not found', 404);
    }
}
