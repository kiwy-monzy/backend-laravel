<?php

namespace Modules\Storage\Http\Controllers;

use App\Http\Controllers\Api\ApiController;
use App\Models\StorageCollection;
use App\Models\Upload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorageApiController extends ApiController
{
    public function collections(Request $request): JsonResponse
    {
        return $this->json([
            'collections' => StorageCollection::where('organization_id', $this->organizationId($request))
                ->withCount('uploads')
                ->orderBy('name')
                ->get()
                ->map(fn (StorageCollection $c) => [
                    'slug' => $c->slug,
                    'name' => $c->name,
                    'description' => $c->description,
                    'min_role' => $c->min_role,
                    'files' => $c->uploads_count,
                    'bytes' => $c->bytes(),
                ])->values(),
        ]);
    }

    public function files(Request $request): JsonResponse
    {
        return $this->json([
            'files' => Upload::where('organization_id', $this->organizationId($request))
                ->when($request->query('collection'), fn ($q, $slug) => $q->whereHas(
                    'collection',
                    fn ($c) => $c->where('slug', $slug),
                ))
                ->orderByDesc('created_at')
                ->limit(min((int) $request->query('limit', 100), 500))
                ->get()
                ->map(fn (Upload $u) => $u->toApi() + ['collection' => $u->collection?->slug])
                ->values(),
        ]);
    }

    /** What a backup would need to copy, and how big it is. */
    public function usage(Request $request): JsonResponse
    {
        $organizationId = (string) $this->organizationId($request);
        $bytes = StorageCollection::organizationBytes($organizationId);

        return $this->json([
            'organization_id' => $organizationId,
            'path' => 'storage/app/public/uploads/' . $organizationId,
            'bytes' => $bytes,
            'megabytes' => round($bytes / 1048576, 2),
            'files' => Upload::where('organization_id', $organizationId)->count(),
        ]);
    }
}
