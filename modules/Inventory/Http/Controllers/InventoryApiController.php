<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Models\Stock;

class InventoryApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user') ?? $request->user();

        return $this->json([
            'records' => Stock::where('organization_id', $user?->organization_id)
                ->orderByDesc('created_at')
                ->limit(min((int) $request->query('limit', 100), 500))
                ->get()
                ->map(fn (Stock $r) => $r->toApi())
                ->values(),
        ]);
    }

    public function show(Request $request, string $record): JsonResponse
    {
        $user = $request->attributes->get('auth_user') ?? $request->user();

        $row = Stock::where('organization_id', $user?->organization_id)->find($record);

        return $row ? $this->json($row->toApi()) : $this->fail('Not found', 404);
    }
}
