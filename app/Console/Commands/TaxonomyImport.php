<?php

namespace App\Console\Commands;

use App\Support\Taxonomy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Loads or refreshes a taxonomy from `resources/taxonomies/`.
 *
 * Drop a `google_product.txt` (Google's official taxonomy export) or a
 * `mow_boq.txt` into that directory and run this — it clears the cache and
 * re-parses, so the full list replaces the bundled subset. With no file
 * present it reports how many rows the fallback provides, so it doubles as a
 * "what am I working with" check.
 */
class TaxonomyImport extends Command
{
    protected $signature = 'taxonomy:import {which? : google_product or mow_boq; both if omitted}';

    protected $description = 'Refresh the product / works taxonomies from resources/taxonomies';

    public function handle(): int
    {
        Taxonomy::forget();

        $which = $this->argument('which');
        $targets = $which ? [$which] : [Taxonomy::GOOGLE, Taxonomy::MOW];

        foreach ($targets as $taxonomy) {
            $path = resource_path("taxonomies/$taxonomy.txt");
            $rows = count(Taxonomy::all($taxonomy));

            if (File::exists($path)) {
                $this->info("$taxonomy: loaded $rows rows from resources/taxonomies/$taxonomy.txt");
            } else {
                $this->warn("$taxonomy: no file — using the bundled $rows-row subset.");
                $this->line("  Drop the full list at resources/taxonomies/$taxonomy.txt and re-run.");
            }
        }

        return self::SUCCESS;
    }
}
