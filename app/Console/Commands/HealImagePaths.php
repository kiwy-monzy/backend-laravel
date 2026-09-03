<?php

namespace App\Console\Commands;

use App\Models\GalleryImage;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Repoint image paths at where the files actually are.
 *
 * When storage was reorganised into `uploads/{organization}/{collection}/`, the
 * files moved but some rows that referenced them did not — so a gallery image
 * whose picture is sitting on disk renders as a broken image, which reads as
 * "the upload failed" rather than "the path is stale".
 *
 * The repair is by filename: for each stale URL, find the file with that name
 * under the organization's own folder and rewrite the path to it. A row whose
 * file genuinely is not on disk is reported and left alone — inventing a path
 * for a missing file would only move the breakage somewhere harder to see.
 */
class HealImagePaths extends Command
{
    protected $signature = 'images:heal {--dry-run : Report what would change without writing}';

    protected $description = 'Repoint gallery, avatar and upload paths at the files on disk.';

    private int $fixed = 0;

    private int $missing = 0;

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        foreach (Organization::all() as $organization) {
            $this->healOrganization($organization, $dry);
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d path(s); %d file(s) genuinely missing.',
            $dry ? 'Would fix' : 'Fixed',
            $this->fixed,
            $this->missing,
        ));

        return self::SUCCESS;
    }

    private function healOrganization(Organization $organization, bool $dry): void
    {
        // Everything this organization actually has on disk, by filename.
        $root = storage_path('app/public/uploads/' . $organization->id);

        if (! is_dir($root)) {
            return;
        }

        $onDisk = [];

        foreach ($this->walk($root) as $absolute) {
            $onDisk[basename($absolute)] = '/storage/uploads/' . $organization->id
                . '/' . str_replace('\\', '/', substr($absolute, strlen($root) + 1));
        }

        if ($onDisk === []) {
            return;
        }

        $sites = \App\Models\Website::where('organization_id', $organization->id)->pluck('id');

        $this->heal(
            GalleryImage::whereIn('website_id', $sites)->get(),
            'url',
            $onDisk,
            $dry,
            'gallery image',
        );

        $this->heal(
            Upload::where('organization_id', $organization->id)->get(),
            'url',
            $onDisk,
            $dry,
            'upload',
        );

        $this->heal(
            OrganizationMember::where('organization_id', $organization->id)->whereNotNull('photo_url')->get(),
            'photo_url',
            $onDisk,
            $dry,
            'team portrait',
        );

        $this->heal(
            User::where('organization_id', $organization->id)->whereNotNull('profile_image')->get(),
            'profile_image',
            $onDisk,
            $dry,
            'user portrait',
        );

        $this->healContent($sites, $onDisk, $dry);
    }

    /**
     * Site content, where the paths are inside a JSON blob.
     *
     * The logo, the hero picture and every portrait in the team section live in
     * `content_sections.data`, so they cannot be healed by rewriting a column —
     * the whole document is walked and each stale path replaced in place.
     */
    private function healContent($sites, array $onDisk, bool $dry): void
    {
        $rows = \App\Models\ContentSection::whereIn('website_id', $sites)->get();

        foreach ($rows as $row) {
            $data = $row->data;

            if (! is_array($data)) {
                continue;
            }

            $changed = 0;
            $healed = $this->walkData($data, $onDisk, $changed);

            if ($changed === 0) {
                continue;
            }

            $this->line("  <fg=green>fix</> content ({$row->section}): {$changed} path(s)");
            $this->fixed += $changed;

            if (! $dry) {
                $row->forceFill(['data' => $healed])->save();
            }
        }
    }

    /** Rewrite every stale `/storage/...` string found anywhere in the data. */
    private function walkData(array $data, array $onDisk, int &$changed): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->walkData($value, $onDisk, $changed);

                continue;
            }

            if (! is_string($value) || ! str_starts_with($value, '/storage/')) {
                continue;
            }

            $absolute = storage_path('app/public' . substr($value, strlen('/storage')));

            if (is_file($absolute)) {
                continue;
            }

            $name = basename($value);

            if (isset($onDisk[$name])) {
                $data[$key] = $onDisk[$name];
                $changed++;
            } else {
                $this->missing++;
            }
        }

        return $data;
    }

    private function heal($records, string $column, array $onDisk, bool $dry, string $label): void
    {
        foreach ($records as $record) {
            $url = (string) $record->{$column};

            if ($url === '' || ! str_starts_with($url, '/storage/')) {
                continue;
            }

            // Already correct? Then nothing to do.
            $absolute = storage_path('app/public' . substr($url, strlen('/storage')));

            if (is_file($absolute)) {
                continue;
            }

            $name = basename($url);

            if (! isset($onDisk[$name])) {
                $this->line("  <fg=yellow>missing</> {$label}: {$name}");
                $this->missing++;

                continue;
            }

            $this->line("  <fg=green>fix</> {$label}: {$name}");
            $this->fixed++;

            if (! $dry) {
                $record->forceFill([$column => $onDisk[$name]])->save();
            }
        }
    }

    /** Every file under a directory, depth first. */
    private function walk(string $directory): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                yield $file->getPathname();
            }
        }
    }
}
