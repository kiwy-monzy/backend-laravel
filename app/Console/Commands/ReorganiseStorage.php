<?php

namespace App\Console\Commands;

use App\Models\ContentSection;
use App\Models\Donation;
use App\Models\GalleryImage;
use App\Models\Organization;
use App\Models\StorageCollection;
use App\Models\Upload;
use App\Services\MediaLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Moves the flat `uploads/` directory into the per-organization layout.
 *
 *     uploads/photo.jpg  →  uploads/{organization_id}/website/photo.jpg
 *
 * **Every reference is rewritten in the same pass.** A move that relocated the
 * files but left `gallery_images.url`, `donations.transaction_image` and the
 * image paths buried in the content JSON pointing at the old location would
 * break every picture on every site — so the rewrite is not a follow-up step,
 * it is the same step.
 *
 * Idempotent: a file already inside an organization folder is left alone.
 */
class ReorganiseStorage extends Command
{
    protected $signature = 'fge:reorganise-storage
                            {--dry-run : Report what would move without touching anything}';

    protected $description = 'Move flat uploads into uploads/{organization}/{collection}/ and rewrite every reference';

    public function handle(MediaLibrary $media): int
    {
        $dry = (bool) $this->option('dry-run');
        $disk = Storage::disk('public');

        $organization = Organization::orderBy('created_at')->first();
        if (! $organization) {
            $this->error('No organization to file storage under.');

            return self::FAILURE;
        }

        StorageCollection::seedFor($organization);
        $website = StorageCollection::where('organization_id', $organization->id)
            ->where('slug', 'website')->firstOrFail();

        $target = $media->directory($organization->id, 'website');
        $moves = [];

        // Only the files sitting directly in `uploads/` are loose; anything
        // deeper is already filed.
        foreach ($disk->files(MediaLibrary::ROOT) as $path) {
            $name = basename($path);
            $to = $target . '/' . $name;

            if ($path === $to) {
                continue;
            }

            $moves[$path] = $to;
        }

        if ($moves === []) {
            $this->info('Nothing to move — storage is already organised.');
        } else {
            $this->line(count($moves) . ' file(s) to move into ' . $target);
        }

        if ($dry) {
            foreach (array_slice($moves, 0, 10, true) as $from => $to) {
                $this->line('  ' . basename($from) . ' → ' . $to);
            }
            $this->warn('Dry run — nothing was written.');

            return self::SUCCESS;
        }

        $disk->makeDirectory($target);

        $rewrites = [];
        foreach ($moves as $from => $to) {
            if ($disk->exists($to)) {
                $disk->delete($from);
            } elseif (! $disk->move($from, $to)) {
                $this->warn('  could not move ' . $from);

                continue;
            }

            $rewrites['/storage/' . $from] = '/storage/' . $to;
        }

        $this->info('Moved ' . count($rewrites) . ' file(s).');

        // Heal anything already pointing at a path that no longer resolves.
        // A move that half-completed, or a reference the first pass missed,
        // leaves a URL whose file is gone — and a broken image is invisible
        // until someone loads the page. Matching on basename is safe because
        // the library gives every stored file a unique name.
        $rewrites += $this->healBrokenReferences($organization->id);

        $touched = $this->rewriteReferences($rewrites, $organization->id, $website->id);

        $this->info("Rewrote $touched reference(s) across uploads, gallery, donations and content.");
        $this->line('Organization storage: ' . $this->human(StorageCollection::organizationBytes($organization->id)));

        return self::SUCCESS;
    }

    /**
     * Finds references whose file has moved and maps them to where it now is.
     *
     * @return array<string,string> old url => new url
     */
    private function healBrokenReferences(string $organizationId): array
    {
        $disk = Storage::disk('public');

        // Every file the organization owns, indexed by basename.
        $byName = [];
        foreach ($disk->allFiles(MediaLibrary::ROOT . '/' . $organizationId) as $path) {
            $byName[basename($path)] = '/storage/' . $path;
        }

        $seen = [];
        $healed = [];

        $consider = function (?string $url) use (&$seen, &$healed, $byName, $disk) {
            if (! $url || ! Str::startsWith($url, MediaLibrary::PREFIX) || isset($seen[$url])) {
                return;
            }
            $seen[$url] = true;

            if ($disk->exists(Str::after($url, '/storage/'))) {
                return;
            }

            $name = basename($url);
            if (isset($byName[$name]) && $byName[$name] !== $url) {
                $healed[$url] = $byName[$name];
            }
        };

        foreach (Upload::cursor() as $row) {
            $consider($row->url);
        }
        foreach (GalleryImage::cursor() as $row) {
            $consider($row->url);
        }
        foreach (Donation::whereNotNull('transaction_image')->cursor() as $row) {
            $consider($row->transaction_image);
        }
        foreach (ContentSection::cursor() as $section) {
            // Copied into a local first: `data` is a cast attribute, and
            // `array_walk_recursive` takes its array by reference — handing it
            // the accessor's return value is a notice and a silent no-op.
            $data = $section->data ?? [];
            array_walk_recursive($data, function ($value) use ($consider) {
                if (is_string($value)) {
                    $consider($value);
                }
            });
        }

        if ($healed !== []) {
            $this->warn('Healing ' . count($healed) . ' reference(s) whose file had moved.');
        }

        return $healed;
    }

    /**
     * Points every stored URL at the new path.
     *
     * @param  array<string,string>  $rewrites  old url => new url
     */
    private function rewriteReferences(array $rewrites, string $organizationId, string $collectionId): int
    {
        $touched = 0;

        foreach (Upload::cursor() as $upload) {
            $url = $rewrites[$upload->url] ?? $upload->url;

            $upload->fill([
                'url' => $url,
                'path' => Str::after($url, '/storage/'),
                'organization_id' => $upload->organization_id ?: $organizationId,
                'collection_id' => $upload->collection_id ?: $collectionId,
            ]);

            if ($upload->isDirty()) {
                $upload->save();
                $touched++;
            }
        }

        foreach (GalleryImage::cursor() as $image) {
            if (isset($rewrites[$image->url])) {
                $image->update(['url' => $rewrites[$image->url]]);
                $touched++;
            }
        }

        foreach (Donation::whereNotNull('transaction_image')->cursor() as $donation) {
            if (isset($rewrites[$donation->transaction_image])) {
                $donation->update(['transaction_image' => $rewrites[$donation->transaction_image]]);
                $touched++;
            }
        }

        // Content sections are free-form JSON, so every image path is found by
        // walking the structure.
        //
        // **Not by string-replacing the encoded document.** `json_encode`
        // escapes `/` as `\/`, so a `/storage/uploads/…` key never matches the
        // encoded text — which is exactly how the first run reported success
        // while leaving every content image pointing at a file that had moved.
        foreach (ContentSection::cursor() as $section) {
            $changed = 0;
            $section->data = $this->rewriteDeep($section->data, $rewrites, $changed);

            if ($changed > 0) {
                $section->save();
                $touched += $changed;
            }
        }

        return $touched;
    }

    /**
     * Replaces every matching URL anywhere in a decoded-JSON structure.
     *
     * @param  array<string,string>  $rewrites
     */
    private function rewriteDeep(mixed $value, array $rewrites, int &$changed): mixed
    {
        if (is_string($value)) {
            if (isset($rewrites[$value])) {
                $changed++;

                return $rewrites[$value];
            }

            return $value;
        }

        if (is_array($value)) {
            // A plain closure with `use (&$changed)`, not `fn` — arrow
            // functions capture by value, so the recursive increments were
            // discarded and the caller saw zero changes on a run that had in
            // fact rewritten every path.
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = $this->rewriteDeep($item, $rewrites, $changed);
            }

            return $out;
        }

        return $value;
    }

    private function human(int $bytes): string
    {
        return $bytes > 1048576
            ? number_format($bytes / 1048576, 1) . ' MB'
            : number_format($bytes / 1024, 1) . ' KB';
    }
}
