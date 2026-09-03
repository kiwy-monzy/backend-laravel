<?php

namespace App\Http\Controllers\Api;

use App\Models\Upload;
use App\Models\Website;
use App\Services\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;

/**
 * The packaged frontend still POSTs `data_url`, so this endpoint keeps
 * accepting one — but it decodes it to a file rather than storing the string.
 * The response shape is unchanged, so no client had to be updated.
 */
class UploadController extends ApiController
{
    public function __construct(private MediaLibrary $media)
    {
    }

    /**
     * @OA\Post(
     *     path="/api/ListUploads",
     *     summary="List all uploads",
     *     tags={"Uploads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of uploads retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="files", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function list(): JsonResponse
    {
        $files = Upload::orderByDesc('created_at')->get()
            ->map(fn (Upload $u) => $u->toApi())
            ->values();

        return $this->json(['files' => $files]);
    }

    /**
     * @OA\Post(
     *     path="/api/UploadFile",
     *     summary="Upload a file",
     *     tags={"Uploads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"filename", "data_url"},
     *             @OA\Property(property="filename", type="string", example="image.jpg"),
     *             @OA\Property(property="data_url", type="string", example="data:image/jpeg;base64,...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File uploaded successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Upload")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request"
     *     )
     * )
     */
    public function create(Request $request): JsonResponse
    {
        $data = $this->body($request);
        $filename = trim($data['filename'] ?? '');
        $dataUrl = $data['data_url'] ?? '';

        if ($filename === '') {
            return $this->fail('filename required', 400);
        }
        if ($dataUrl === '') {
            return $this->fail('data_url required', 400);
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? '_';
        $url = $this->media->materialise($dataUrl, $safeName, $this->organizationId($request), 'website');

        if (str_starts_with($url, 'data:')) {
            return $this->fail('data_url could not be decoded', 400);
        }

        $path = Str::after($url, '/storage/');

        $upload = Upload::create([
            'id' => (string) Str::uuid(),
            'website_id' => Website::FGE_WEBSITE_ID,
            'organization_id' => $this->organizationId($request),
            'path' => $path,
            'filename' => $safeName,
            'mime' => Storage::disk('public')->mimeType($path) ?: $this->guessMime($safeName),
            'size' => Storage::disk('public')->size($path),
            'url' => $url,
            'created_at' => now(),
        ]);

        return $this->json($upload->toApi());
    }

    /**
     * @OA\Post(
     *     path="/api/DeleteUpload",
     *     summary="Delete an upload",
     *     tags={"Uploads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id"},
     *             @OA\Property(property="id", type="string", example="uuid-here")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Upload deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     )
     * )
     */
    public function delete(Request $request): JsonResponse
    {
        $data = $this->body($request);
        $upload = Upload::find($data['id'] ?? '');
        if (! $upload) {
            return $this->json(['success' => false]);
        }

        $url = $upload->url;
        $upload->delete();

        // Only unlink once no other row claims the same path.
        if (Str::startsWith($url, MediaLibrary::PREFIX) && ! Upload::where('url', $url)->exists()) {
            Storage::disk('public')->delete(Str::after($url, '/storage/'));
        }

        return $this->json(['success' => true]);
    }

    private function guessMime(string $filename): string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
            'ico' => 'image/x-icon',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            default => 'application/octet-stream',
        };
    }
}
