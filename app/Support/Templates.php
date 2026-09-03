<?php

namespace App\Support;

/**
 * The five public-site layouts.
 *
 * **They differ in arrangement, not in data.** Every template renders the same
 * eleven content sections through the same partials; what changes is the shell
 * — where the nav sits, how the hero is framed, whether cards are flat or
 * elevated. That is what makes a template switch safe: no site can lose
 * content by changing its look.
 *
 * `template1` is the port of the original React frontend, kept pixel-faithful
 * because FGE runs on it.
 */
final class Templates
{
    /**
     * Templates come in two collections.
     *
     * `custom` holds hand-built ports of a specific site's design — they carry
     * their own stylesheet and their own nav, hero and footer, so they are not
     * expected to look like anything else. `standard` holds the generic five,
     * which share one stylesheet and differ only by arrangement.
     *
     * The distinction is not cosmetic: a change to `site.css` affects every
     * standard template and no custom one.
     */
    public const COLLECTIONS = [
        'custom' => 'Custom',
        'standard' => 'Standard',
    ];

    public const ALL = [
        'template0' => [
            'label' => 'FGE Original',
            'description' => 'A faithful port of the FGE React frontend: floating pill navbar, masked background grid in the hero, drifting colour blobs and the animated tri-colour headline.',
            'default_theme' => 'fge-custom',
            'collection' => 'custom',
        ],
        'template1' => [
            'label' => 'Classic (FGE)',
            'description' => 'The original FGE layout: gradient page, sticky translucent navbar, full-bleed hero.',
            'default_theme' => 'fge',
            'collection' => 'standard',
        ],
        'template2' => [
            'label' => 'Editorial',
            'description' => 'Serif headings, wide measure, left-aligned hero over a plain background.',
            'default_theme' => 'slate',
            'collection' => 'standard',
        ],
        'template3' => [
            'label' => 'Studio',
            'description' => 'Dark shell with a light content well and oversized section numbers.',
            'default_theme' => 'royal',
            'collection' => 'standard',
        ],
        'template4' => [
            'label' => 'Campaign',
            'description' => 'Split hero with the donate call to action pinned beside it throughout.',
            'default_theme' => 'sunset',
            'collection' => 'standard',
        ],
        'template5' => [
            'label' => 'Compact',
            'description' => 'Single-column, card-free, tuned for slow connections and small screens.',
            'default_theme' => 'ocean',
            'collection' => 'standard',
        ],
    ];

    public static function labels(): array
    {
        return array_map(fn (array $t) => $t['label'], self::ALL);
    }

    /**
     * The templates grouped for the picker, custom first.
     *
     * @return array<string,array<string,array>>
     */
    public static function byCollection(): array
    {
        $grouped = array_fill_keys(array_keys(self::COLLECTIONS), []);

        foreach (self::ALL as $key => $template) {
            $grouped[$template['collection'] ?? 'standard'][$key] = $template;
        }

        return array_filter($grouped);
    }

    public static function exists(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::ALL);
    }

    /** Falls back rather than throwing: a bad value in the column must not 500 the public site. */
    public static function resolve(?string $key): string
    {
        return self::exists($key) ? $key : 'template1';
    }

    public static function defaultTheme(?string $key): string
    {
        return self::ALL[self::resolve($key)]['default_theme'];
    }
}
