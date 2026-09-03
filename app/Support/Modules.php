<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * The module registry.
 *
 * **A module is a directory, not an entry in a list.** Everything the app
 * knows about `modules/Crm` it reads from `modules/Crm/module.json` at boot —
 * so adding a feature is `php artisan module:init Crm` and writing code, with
 * no central file to remember to edit. That is the whole reason for the
 * layout: the previous arrangement put every controller in one `app/Http`
 * tree, and the invoicing port would have doubled its size.
 *
 * Each module owns its own routes, models, views and migrations. The only
 * things it does not own are the organization, the user and the permission
 * matrix, which live in `app/` because every module gates on them.
 */
final class Modules
{
    /** Cached so a request that renders the nav does not stat the disk repeatedly. */
    private static ?array $cache = null;

    public static function path(string $sub = ''): string
    {
        return base_path('modules' . ($sub ? DIRECTORY_SEPARATOR . $sub : ''));
    }

    /**
     * Every installed module, keyed by slug, ordered by the `order` in its manifest.
     *
     * @return array<string,array>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $modules = [];

        if (File::isDirectory(self::path())) {
            foreach (File::directories(self::path()) as $dir) {
                $manifest = $dir . DIRECTORY_SEPARATOR . 'module.json';
                if (! File::exists($manifest)) {
                    continue;
                }

                $data = json_decode(File::get($manifest), true);
                if (! is_array($data) || empty($data['slug'])) {
                    // A malformed manifest must not take the whole app down —
                    // `module:list` is where you find out about it.
                    continue;
                }

                $data['name'] = $data['name'] ?? basename($dir);
                $data['path'] = $dir;
                $data['enabled'] = $data['enabled'] ?? true;
                $data['order'] = $data['order'] ?? 100;

                $modules[$data['slug']] = $data;
            }
        }

        uasort($modules, fn ($a, $b) => [$a['order'], $a['slug']] <=> [$b['order'], $b['slug']]);

        return self::$cache = $modules;
    }

    /** @return array<string,array> only the modules that are switched on */
    public static function enabled(): array
    {
        return array_filter(self::all(), fn (array $m) => $m['enabled']);
    }

    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    public static function exists(string $slug): bool
    {
        return isset(self::all()[$slug]);
    }

    /** Slugs of every enabled module — the vocabulary the permission matrix uses. */
    public static function slugs(): array
    {
        return array_keys(self::enabled());
    }

    public static function label(string $slug): string
    {
        return self::find($slug)['label'] ?? ucfirst(str_replace('-', ' ', $slug));
    }

    /**
     * The minimum plan a module needs, or null when it is always available.
     *
     * System modules (team, settings, subscription) return null deliberately:
     * an organization whose trial has lapsed must still be able to reach its
     * own billing page to fix that.
     */
    public static function requiresPlan(string $slug): ?string
    {
        return self::find($slug)['requires_plan'] ?? null;
    }

    public static function forget(): void
    {
        self::$cache = null;
    }
}
