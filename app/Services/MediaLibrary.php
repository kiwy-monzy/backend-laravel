<?php

namespace App\Services;

use App\Models\StorageCollection;
use App\Models\Upload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The one place a file becomes a URL.
 *
 * **Nothing is stored as base64, and nothing is stored loose.** The Rust
 * server kept uploads as `data:` URLs inside the content JSON, which meant a
 * page of thumbnails shipped megabytes of un-cacheable text; the first Laravel
 * pass fixed that but put every organization's files in one flat directory,
 * which made per-tenant backup and quota unanswerable.
 *
 * Everything now lands at:
 *
 *     storage/app/public/uploads/{organization_id}/{collection}/{file}
 *
 * so one organization's storage is one directory. `materialise()` is the
 * bridge: hand it a `data:` URL or a path and it returns a path, writing the
 * file only when it was handed base64 — which lets the importer, the API and
 * the admin forms share one code path.
 */
class MediaLibrary
{
    public const ROOT = 'uploads';

    /** Public path prefix as the browser sees it. */
    public const PREFIX = '/storage/uploads/';

    /** Legacy constant for ConsolidateAssets command. */
    public const DIR = 'uploads';

    /**
     * Where a file goes.
     *
     * Falls back to a `_shared` folder when there is no organization — the
     * legacy importer runs before tenancy is established — rather than
     * writing to the root, so the flat directory can never come back.
     */
    public function directory(?string $organizationId, string $collection = 'website'): string
    {
        return self::ROOT . '/' . ($organizationId ?: '_shared') . '/' . $collection;
    }

    /** Saves an uploaded file into a collection and returns its details. */
    public function storeUpload(
        UploadedFile $file,
        ?string $organizationId = null,
        string $collection = 'website',
    ): array {
        $name = $this->safeName($file->getClientOriginalName() ?: 'file');
        $path = $this->directory($organizationId, $collection) . '/' . $this->unique($name);

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        return [
            'url' => '/storage/' . $path,
            'path' => $path,
            'filename' => $name,
            'mime' => $file->getClientMimeType() ?: $this->guessMime($name),
            'size' => $file->getSize() ?: Storage::disk('public')->size($path),
        ];
    }

    /**
     * Registers a stored file as an `uploads` row.
     *
     * Keyed on the path so re-running an import does not create a second row
     * for a file that is already indexed.
     */
    public function register(array $stored, ?string $organizationId, ?StorageCollection $collection): Upload
    {
        return Upload::firstOrCreate(['url' => $stored['url']], [
            'id' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'collection_id' => $collection?->id,
            'path' => $stored['path'],
            'filename' => $stored['filename'],
            'mime' => $stored['mime'],
            'size' => $stored['size'],
            'created_at' => now(),
        ]);
    }

    /**
     * Turns a `data:` URL into a stored file; passes anything else through.
     *
     * Returns the original when handed something it cannot decode, so callers
     * never lose a value they did not understand.
     */
    public function materialise(
        mixed $value,
        string $hint = 'image',
        ?string $organizationId = null,
        string $collection = 'website',
    ): mixed {
        if (! is_string($value) || ! str_starts_with($value, 'data:')) {
            return $value;
        }

        $decoded = $this->decodeDataUrl($value);
        if ($decoded === null) {
            // Malformed base64: keep the original rather than replacing real
            // content with an empty file.
            return $value;
        }

        [$bytes, $mime] = $decoded;
        $name = $this->safeName($hint) . '.' . $this->extensionFor($mime);
        $path = $this->directory($organizationId, $collection) . '/' . $this->unique($name);

        Storage::disk('public')->put($path, $bytes);

        return '/storage/' . $path;
    }

    /**
     * Walks a decoded-JSON structure replacing every embedded data URL.
     *
     * Content sections are free-form — hero images, team portraits, project
     * cards — so there is no list of fields to check; recursion is what
     * actually catches them all.
     *
     * @param  int  $converted  running count, by reference so the caller can report
     */
    public function materialiseDeep(
        mixed $value,
        int &$converted = 0,
        string $hint = 'content',
        ?string $organizationId = null,
        string $collection = 'website',
    ): mixed {
        if (is_string($value)) {
            if (! str_starts_with($value, 'data:')) {
                return $value;
            }
            $new = $this->materialise($value, $hint, $organizationId, $collection);
            if ($new !== $value) {
                $converted++;
            }

            return $new;
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = $this->materialiseDeep(
                    $item,
                    $converted,
                    is_string($key) ? $key : $hint,
                    $organizationId,
                    $collection,
                );
            }

            return $out;
        }

        return $value;
    }

    /** Copies a file already on disk into a collection. Returns its public path. */
    public function adopt(string $absolutePath, ?string $organizationId = null, string $collection = 'website'): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $name = $this->safeName(basename($absolutePath));
        $directory = $this->directory($organizationId, $collection);
        $path = $directory . '/' . $name;

        // Same name and same size means the same file — re-copying it on every
        // run would fill the disk with numbered duplicates.
        if (Storage::disk('public')->exists($path)
            && Storage::disk('public')->size($path) === filesize($absolutePath)) {
            return '/storage/' . $path;
        }

        if (Storage::disk('public')->exists($path)) {
            $path = $directory . '/' . $this->unique($name);
        }

        Storage::disk('public')->put($path, file_get_contents($absolutePath));

        return '/storage/' . $path;
    }

    /** Deletes a stored file, but only once nothing else points at it. */
    public function forget(Upload $upload): void
    {
        $url = $upload->url;
        $path = $upload->path ?: Str::after($url, '/storage/');

        $upload->delete();

        if (Str::startsWith($url, self::PREFIX) && ! Upload::where('url', $url)->exists()) {
            Storage::disk('public')->delete($path);
        }
    }

    /** @return array{string,string}|null [$bytes, $mime] */
    private function decodeDataUrl(string $value): ?array
    {
        if (! preg_match('/^data:([^;,]*)?(;base64)?,(.*)$/s', $value, $m)) {
            return null;
        }

        $mime = $m[1] ?: 'application/octet-stream';
        $payload = $m[3];
        $bytes = $m[2] ? base64_decode($payload, true) : rawurldecode($payload);

        return $bytes === false || $bytes === '' ? null : [$bytes, $mime];
    }

    private function unique(string $name): string
    {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $stem = pathinfo($name, PATHINFO_FILENAME);

        return $stem . '-' . Str::lower(Str::random(8)) . ($ext ? '.' . $ext : '');
    }

    private function safeName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'file';

        return Str::limit($name, 90, '');
    }

    private function extensionFor(string $mime): string
    {
        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/bmp' => 'bmp',
            'image/x-icon', 'image/vnd.microsoft.icon' => 'ico',
            'application/pdf' => 'pdf',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'audio/mpeg' => 'mp3',
            default => 'bin',
        };
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
            'mp3' => 'audio/mpeg',
            default => 'application/octet-stream',
        };
    }
}
