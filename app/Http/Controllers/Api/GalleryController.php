<?php

namespace App\Http\Controllers\Api;

use App\Models\GalleryImage;
use App\Models\Website;
use App\Services\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GalleryController extends ApiController
{
    public function __construct(private MediaLibrary $media)
    {
    }

    public function list(): JsonResponse
    {
        $images = GalleryImage::query()
            ->orderBy('created_at')
            ->get()
            ->map(fn (GalleryImage $g) => $this->toApi($g))
            ->values();

        return $this->json(['images' => $images]);
    }

    public function create(Request $request): JsonResponse
    {
        $data = $this->body($request);
        $url = trim($data['url'] ?? '');
        if ($url === '') {
            return $this->fail('url required', 400);
        }

        // The gallery is always served from Laravel storage; a data: URL
        // handed to this endpoint becomes a file before the row is written.
        $url = $this->media->materialise($url, 'gallery', $this->organizationId($request), 'website');

        $image = GalleryImage::create([
            'id' => ! empty($data['id']) ? $data['id'] : (string) Str::uuid(),
            'website_id' => Website::FGE_WEBSITE_ID,
            'url' => $url,
            'caption' => $data['caption'] ?? '',
            'disabled' => (bool) ($data['disabled'] ?? false),
        ]);

        return $this->json($this->toApi($image));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $this->body($request);
        $image = GalleryImage::find($data['id'] ?? '');
        if (! $image) {
            return $this->fail('Not found', 404);
        }

        if (array_key_exists('url', $data) && $data['url'] !== null) {
            $image->url = $this->media->materialise($data['url'], 'gallery', $this->organizationId($request), 'website');
        }
        if (array_key_exists('caption', $data) && $data['caption'] !== null) {
            $image->caption = $data['caption'];
        }
        if (array_key_exists('disabled', $data) && $data['disabled'] !== null) {
            $image->disabled = (bool) $data['disabled'];
        }
        $image->save();

        return $this->json($this->toApi($image));
    }

    public function delete(Request $request): JsonResponse
    {
        $data = $this->body($request);
        $removed = GalleryImage::destroy($data['id'] ?? '') > 0;

        return $this->json(['success' => $removed]);
    }

    private function toApi(GalleryImage $g): array
    {
        return [
            'id' => $g->id,
            'url' => $g->url,
            'caption' => $g->caption,
            'disabled' => $g->disabled,
        ];
    }
}