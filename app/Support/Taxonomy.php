<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Product and works classifications, loaded once and cached.
 *
 * **Two taxonomies, one loader.** Items and assets classify against the Google
 * product taxonomy; contract works classify against the Tanzania Ministry of
 * Works bill-of-quantities structure. Both are large, static reference data —
 * exactly the thing that should be parsed once at boot and served from cache,
 * not read off disk on every item form.
 *
 * Each is a flat list of `id => "A > B > C"` paths. A full Google taxonomy
 * (~5,500 rows) drops in as `resources/taxonomies/google_product.txt` and is
 * picked up automatically; without it, the curated subset below keeps every
 * form working. `php artisan taxonomy:import` refreshes the cache after a file
 * is added.
 */
final class Taxonomy
{
    public const GOOGLE = 'google_product';

    public const MOW = 'mow_boq';

    private const CACHE_TTL = 86400;

    /** The full map for a taxonomy: `id => "Full > Path"`. */
    public static function all(string $taxonomy): array
    {
        return Cache::remember("taxonomy.$taxonomy", self::CACHE_TTL, function () use ($taxonomy) {
            $fromFile = self::loadFile($taxonomy);

            return $fromFile !== [] ? $fromFile : self::bundled($taxonomy);
        });
    }

    /** Options for a `<select>`: value is the id, label is the path. */
    public static function options(string $taxonomy): array
    {
        return self::all($taxonomy);
    }

    /** The top-level branches, for a two-step picker. */
    public static function roots(string $taxonomy): array
    {
        $roots = [];
        foreach (self::all($taxonomy) as $id => $path) {
            $top = trim(explode('>', $path)[0]);
            $roots[$top] = true;
        }

        return array_keys($roots);
    }

    /** Everything under a top-level branch. */
    public static function under(string $taxonomy, string $root): array
    {
        return array_filter(
            self::all($taxonomy),
            fn (string $path) => str_starts_with($path, $root),
        );
    }

    public static function label(string $taxonomy, ?string $id): string
    {
        return $id ? (self::all($taxonomy)[$id] ?? $id) : '';
    }

    public static function forget(): void
    {
        Cache::forget('taxonomy.' . self::GOOGLE);
        Cache::forget('taxonomy.' . self::MOW);
    }

    /**
     * Parses a taxonomy file if one has been supplied.
     *
     * Accepts the two shapes these files come in: Google's `123 - A > B` and a
     * plain `A > B` list (the id is then the line number). Comment and blank
     * lines are skipped.
     *
     * @return array<string,string>
     */
    private static function loadFile(string $taxonomy): array
    {
        $path = resource_path("taxonomies/$taxonomy.txt");

        if (! File::exists($path)) {
            return [];
        }

        $map = [];
        $line = 0;

        foreach (preg_split('/\r?\n/', File::get($path)) as $row) {
            $line++;
            $row = trim($row);

            if ($row === '' || str_starts_with($row, '#')) {
                continue;
            }

            if (preg_match('/^(\d+)\s*-\s*(.+)$/', $row, $m)) {
                $map[$m[1]] = trim($m[2]);
            } else {
                $map[(string) $line] = $row;
            }
        }

        return $map;
    }

    /**
     * The curated fallback set, so the forms work before any file is loaded.
     *
     * @return array<string,string>
     */
    private static function bundled(string $taxonomy): array
    {
        return $taxonomy === self::MOW ? self::bundledMow() : self::bundledGoogle();
    }

    /**
     * A representative slice of the Google product taxonomy — the branches a
     * Tanzanian general supplier actually sells from.
     */
    private static function bundledGoogle(): array
    {
        $paths = [
            'Business & Industrial',
            'Business & Industrial > Construction',
            'Business & Industrial > Construction > Building Materials',
            'Business & Industrial > Construction > Building Materials > Cement, Mortar & Concrete Mixes',
            'Business & Industrial > Construction > Building Materials > Insulation',
            'Business & Industrial > Construction > Building Materials > Lumber & Sheet Stock',
            'Business & Industrial > MRO & Industrial Supply',
            'Business & Industrial > Work Safety Protective Gear',
            'Electronics',
            'Electronics > Computers',
            'Electronics > Computers > Desktop Computers',
            'Electronics > Computers > Laptops',
            'Electronics > Computers > Computer Servers',
            'Electronics > Computer Accessories',
            'Electronics > Computer Components',
            'Electronics > Networking',
            'Electronics > Print, Copy, Scan & Fax',
            'Electronics > Print, Copy, Scan & Fax > Printers, Copiers & Fax Machines',
            'Furniture',
            'Furniture > Office Furniture',
            'Furniture > Office Furniture > Desks',
            'Furniture > Office Furniture > Office Chairs',
            'Hardware',
            'Hardware > Building Consumables',
            'Hardware > Building Consumables > Chemicals',
            'Hardware > Building Consumables > Hardware Glue & Adhesives',
            'Hardware > Building Consumables > Nails, Screws & Fasteners',
            'Hardware > Building Consumables > Painting Consumables',
            'Hardware > Power & Electrical Supplies',
            'Hardware > Power & Electrical Supplies > Electrical Cables',
            'Hardware > Power & Electrical Supplies > Wire Terminals & Connectors',
            'Hardware > Plumbing',
            'Hardware > Plumbing > Plumbing Fittings & Supports',
            'Hardware > Plumbing > Plumbing Pipes',
            'Hardware > Tools',
            'Hardware > Tools > Hand Tools',
            'Hardware > Tools > Power Tools',
            'Office Supplies',
            'Office Supplies > General Office Supplies',
            'Vehicles & Parts',
            'Vehicles & Parts > Vehicle Parts & Accessories',
        ];

        // Stable ids from a hash, so a stored id keeps meaning if the list is
        // reordered — a plain index would not.
        $map = [];
        foreach ($paths as $path) {
            $map[(string) (crc32($path) & 0x7fffffff)] = $path;
        }

        return $map;
    }

    /**
     * The Tanzania Ministry of Works bill-of-quantities elements.
     *
     * Follows the standard building BoQ structure (element, then sub-element),
     * which is what a construction contract's activities are costed against.
     */
    private static function bundledMow(): array
    {
        $paths = [
            'A - Preliminaries',
            'A - Preliminaries > Site establishment',
            'A - Preliminaries > Insurances & bonds',
            'A - Preliminaries > Supervision & staff',
            'B - Substructure',
            'B - Substructure > Site clearance',
            'B - Substructure > Excavation & earthworks',
            'B - Substructure > Concrete work',
            'B - Substructure > Damp proofing',
            'C - Superstructure',
            'C - Superstructure > Frame',
            'C - Superstructure > Walling',
            'C - Superstructure > Roof',
            'C - Superstructure > Staircases',
            'D - Finishes',
            'D - Finishes > Floor finishes',
            'D - Finishes > Wall finishes',
            'D - Finishes > Ceiling finishes',
            'E - Fittings & Furnishings',
            'F - Services',
            'F - Services > Plumbing & drainage',
            'F - Services > Electrical installation',
            'F - Services > Mechanical & HVAC',
            'F - Services > Fire protection',
            'G - External Works',
            'G - External Works > Roads & paving',
            'G - External Works > Drainage',
            'G - External Works > Landscaping',
            'G - External Works > Boundary walls & fencing',
            'H - Provisional & Prime Cost Sums',
        ];

        $map = [];
        foreach ($paths as $i => $path) {
            // MOW ids are the section code plus a sequence, e.g. B-04.
            $section = substr($path, 0, 1);
            $map[sprintf('%s-%02d', $section, $i)] = $path;
        }

        return $map;
    }
}
