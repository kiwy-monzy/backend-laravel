<?php

namespace App\Console\Commands;

use App\Models\ContentSection;
use App\Models\Donation;
use App\Models\GalleryImage;
use App\Models\Upload;
use App\Models\Website;
use App\Services\MediaLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Moves every asset into Laravel's storage and takes base64 out of the database.
 *
 * Two jobs, both idempotent so it is safe to re-run:
 *
 *  1. **Adopt** files still sitting in the old projects (`fge-frontend/public`,
 *     `fge-frontend/dist/uploads`, `fge-frontend/src/assets`) into
 *     `storage/app/public/uploads`, registering each as an `uploads` row.
 *  2. **Materialise** every `data:` URL held in `uploads.url`,
 *     `gallery_images.url`, `donations.transaction_image` and anywhere inside
 *     the content-section JSON, writing the bytes to disk and leaving a
 *     `/storage/uploads/…` path behind.
 *
 * The second is the one that matters: the Rust server inlined uploads as
 * base64, so a single gallery page shipped megabytes of un-cacheable text and
 * the SQLite file grew without bound.
 *
 * `--prune` additionally clears the adopted copies out of the old projects,
 * moving them to `backups/pre-laravel-assets/`. It is off by default because
 * the first run should be inspectable before anything moves.
 */
class ConsolidateAssets extends Command
{
    protected $signature = 'fge:consolidate-assets
                            {--prune : Clear the source copies (moved to backups/pre-laravel-assets) once adopted}
                            {--dry-run : Report what would change without writing anything}';

    protected $description = 'Move all assets into Laravel storage and convert base64 URLs into files';

    /**
     * Where the old projects kept *uploaded* files, relative to the Laravel root.
     *
     * `fge-frontend/src/assets` is deliberately not here. It looks like the
     * same kind of directory but it is source: its files are `import`ed by
     * components, so moving them out does not free anything — it breaks the
     * frontend's build.
     */
    private const SOURCES = [
        '../fge-frontend/public',
        '../fge-frontend/dist/uploads',
        '../fge-backend/uploads',
    ];

    private const SKIP = ['robots.txt', 'index.html', 'manifest.webmanifest', '.gitkeep'];

    public function handle(MediaLibrary $media): int
    {
        $dry = (bool) $this->option('dry-run');

        if ($dry) {
            $this->warn('Dry run — nothing will be written.');
        }

        Storage::disk('public')->makeDirectory(MediaLibrary::DIR);

        $adopted = $this->adoptSourceFiles($media, $dry);
        $converted = $this->convertInlineData($media, $dry);
        $registered = $this->registerOrphanFiles($dry);

        $this->newLine();
        $this->info("Adopted $adopted file(s) into storage/app/public/uploads.");
        $this->info("Converted $converted base64 value(s) into files.");
        $this->info("Registered $registered file(s) that were on disk but not in the index.");

        if ($this->option('prune') && ! $dry) {
            $removed = $this->prune();
            $this->info("Cleared $removed source file(s) from the old projects.");
        } elseif ($this->option('prune')) {
            $this->warn('--prune skipped: it does not run under --dry-run.');
        }

        return self::SUCCESS;
    }

    /** Copies files out of the legacy project folders and registers them. */
    private function adoptSourceFiles(MediaLibrary $media, bool $dry): int
    {
        $count = 0;

        foreach (self::SOURCES as $relative) {
            $dir = realpath(base_path($relative));
            if (! $dir || ! is_dir($dir)) {
                continue;
            }

            $this->line("Scanning $relative …");

            foreach ($this->filesIn($dir) as $file) {
                $name = basename($file);
                if (in_array($name, self::SKIP, true) || str_starts_with($name, '.')) {
                    continue;
                }

                if ($dry) {
                    $this->line("  would adopt $name");
                    $count++;
                    continue;
                }

                $url = $media->adopt($file);
                if ($url === null) {
                    continue;
                }

                // firstOrCreate keyed on the URL: re-running must not create a
                // second row for a file that is already registered.
                Upload::firstOrCreate(['url' => $url], [
                    'id' => (string) Str::uuid(),
                    'website_id' => Website::FGE_WEBSITE_ID,
                    'filename' => $name,
                    'mime' => $this->mimeOf($file),
                    'size' => filesize($file) ?: 0,
                    'created_at' => now(),
                ]);

                $count++;
            }
        }

        return $count;
    }

    /**
     * Indexes files sitting in storage that no `uploads` row points at.
     *
     * The disk is the source of truth for the bytes; the table is only an
     * index over it. They come apart whenever the database is rebuilt from the
     * legacy import — the files survive, the rows do not — and an unindexed
     * file is one the Storage page cannot show, so it may as well not exist.
     */
    private function registerOrphanFiles(bool $dry): int
    {
        $known = Upload::pluck('url')->flip();
        $count = 0;

        foreach (Storage::disk('public')->files(MediaLibrary::DIR) as $path) {
            $url = '/storage/' . $path;
            if ($known->has($url)) {
                continue;
            }

            if ($dry) {
                $this->line('  would register ' . basename($path));
                $count++;
                continue;
            }

            Upload::create([
                'id' => (string) Str::uuid(),
                'website_id' => Website::FGE_WEBSITE_ID,
                'filename' => basename($path),
                'mime' => Storage::disk('public')->mimeType($path) ?: 'application/octet-stream',
                'size' => Storage::disk('public')->size($path),
                'url' => $url,
                'created_at' => now(),
            ]);

            $count++;
        }

        return $count;
    }

    /** Rewrites every base64 value still held in the database. */
    private function convertInlineData(MediaLibrary $media, bool $dry): int
    {
        $converted = 0;

        foreach (Upload::where('url', 'like', 'data:%')->cursor() as $upload) {
            if ($dry) {
                $this->line("  would convert upload {$upload->filename}");
                $converted++;
                continue;
            }

            $url = $media->materialise($upload->url, $upload->filename ?: 'upload');
            if ($url !== $upload->url) {
                $upload->url = $url;
                $upload->size = Storage::disk('public')->size(Str::after($url, '/storage/'));
                $upload->save();
                $converted++;
            }
        }

        foreach (GalleryImage::where('url', 'like', 'data:%')->cursor() as $image) {
            if ($dry) {
                $this->line("  would convert gallery image {$image->id}");
                $converted++;
                continue;
            }

            $url = $media->materialise($image->url, 'gallery');
            if ($url !== $image->url) {
                $image->url = $url;
                $image->save();
                $converted++;
            }
        }

        foreach (Donation::where('transaction_image', 'like', 'data:%')->cursor() as $donation) {
            if ($dry) {
                $this->line("  would convert donation proof {$donation->id}");
                $converted++;
                continue;
            }

            $url = $media->materialise($donation->transaction_image, 'donation-proof');
            if ($url !== $donation->transaction_image) {
                $donation->transaction_image = $url;
                $donation->save();
                $converted++;
            }
        }

        // Content sections are free-form JSON, so the only reliable way to find
        // an embedded image is to walk the whole structure.
        foreach (ContentSection::cursor() as $section) {
            $encoded = json_encode($section->data);
            if (! str_contains((string) $encoded, 'data:')) {
                continue;
            }

            if ($dry) {
                $this->line("  would convert images inside section {$section->section}");
                $converted++;
                continue;
            }

            $n = 0;
            $section->data = $media->materialiseDeep($section->data, $n, $section->section);
            if ($n > 0) {
                $section->save();
                $converted += $n;
            }
        }

        return $converted;
    }

    /**
     * Clears the source copies now that storage holds them.
     *
     * **Moved into `backups/pre-laravel-assets/`, not unlinked.** Storage is now
     * the only copy of these files and none of them are in git, so a mistake in
     * the name-and-size match below would be unrecoverable. Moving costs
     * nothing and leaves the old projects just as empty.
     *
     * Only files whose bytes are already in the library are touched — a source
     * file that failed to copy stays exactly where it is.
     */
    private function prune(): int
    {
        $moved = 0;
        $backupRoot = base_path('../backups/pre-laravel-assets');

        foreach (self::SOURCES as $relative) {
            $dir = realpath(base_path($relative));
            if (! $dir || ! is_dir($dir)) {
                continue;
            }

            foreach ($this->filesIn($dir) as $file) {
                $name = basename($file);
                if (in_array($name, self::SKIP, true) || str_starts_with($name, '.')) {
                    continue;
                }

                $target = MediaLibrary::DIR . '/' . preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
                if (! Storage::disk('public')->exists($target)
                    || Storage::disk('public')->size($target) !== filesize($file)) {
                    continue;
                }

                $destination = $backupRoot . '/' . trim(str_replace('..', '', $relative), '/') . '/'
                    . ltrim(str_replace($dir, '', $file), '\\/');

                @mkdir(dirname($destination), 0775, true);
                if (@rename($file, $destination)) {
                    $moved++;
                }
            }
        }

        $this->line("Source copies moved to backups/pre-laravel-assets/.");

        return $moved;
    }

    /** @return \Generator<string> */
    private function filesIn(string $dir): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $entry) {
            if ($entry->isFile()) {
                yield $entry->getPathname();
            }
        }
    }

    private function mimeOf(string $path): string
    {
        $type = @mime_content_type($path);

        return $type ?: 'application/octet-stream';
    }
}
