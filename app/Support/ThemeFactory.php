<?php

namespace App\Support;

use App\Models\Website;

/**
 * Turns a site's chosen palette into the CSS custom properties every public
 * template reads.
 *
 * **One source of colour, derived rather than listed.** The old frontend
 * hard-coded eleven emerald hex values in `globals.css` and three more in the
 * `theme` content section, which is why changing the brand colour never quite
 * worked. Here a preset is three seed colours and everything else — hover,
 * light, ring, gradient stops — is computed from them, so a template author
 * only ever styles against the variable names.
 *
 * Precedence, weakest first: preset → the site's `theme` content section (what
 * the legacy admin UI wrote) → `websites.theme_overrides` (hand edits).
 */
final class ThemeFactory
{
    /**
     * The preset palettes, as [primary, secondary, tertiary].
     *
     * `fge` reproduces the emerald the live FGE site already uses; it is the
     * default precisely so that FGE renders unchanged.
     */
    public const PRESETS = [
        // The palette the live FGE site actually renders in — the red-to-pink
        // gradient from the React frontend, with teal as the third stop. It is
        // separate from `fge` (the emerald in the old globals.css defaults)
        // because both are "FGE" to somebody and conflating them is what makes
        // a brand colour un-findable.
        'fge-custom' => ['label' => 'FGE Original', 'colors' => ['#e42f2f', '#e9267c', '#0d9488']],
        'fge' => ['label' => 'FGE Emerald', 'colors' => ['#10b981', '#059669', '#047857']],
        'ocean' => ['label' => 'Ocean', 'colors' => ['#0ea5e9', '#0284c7', '#0369a1']],
        'sunset' => ['label' => 'Sunset', 'colors' => ['#f97316', '#ea580c', '#c2410c']],
        'royal' => ['label' => 'Royal', 'colors' => ['#8b5cf6', '#7c3aed', '#6d28d9']],
        'rose' => ['label' => 'Rose', 'colors' => ['#f43f5e', '#e11d48', '#be123c']],
        'slate' => ['label' => 'Slate', 'colors' => ['#475569', '#334155', '#1e293b']],
        'gold' => ['label' => 'Gold', 'colors' => ['#d97706', '#b45309', '#92400e']],
    ];

    public static function labels(): array
    {
        return array_map(fn (array $p) => $p['label'], self::PRESETS);
    }

    /** The three seed colours for a site, after every override has been applied. */
    public static function seeds(Website $website): array
    {
        $preset = self::PRESETS[$website->effectiveTheme()] ?? self::PRESETS['fge'];
        [$primary, $secondary, $tertiary] = $preset['colors'];

        // What the legacy admin UI wrote. Honoured so that an existing site's
        // colours survive the move to templates.
        $section = $website->sectionData('theme') ?? [];
        $primary = self::hex($section['primary_color'] ?? null) ?? $primary;
        $secondary = self::hex($section['secondary_color'] ?? null) ?? $secondary;
        $tertiary = self::hex($section['tertiary_color'] ?? null) ?? $tertiary;

        $overrides = $website->effectiveThemeOverrides();
        $primary = self::hex($overrides['primary'] ?? null) ?? $primary;
        $secondary = self::hex($overrides['secondary'] ?? null) ?? $secondary;
        $tertiary = self::hex($overrides['tertiary'] ?? null) ?? $tertiary;

        return [$primary, $secondary, $tertiary];
    }

    /**
     * The full variable set, as a `name => value` map.
     *
     * The names match the ones the React frontend used, so the ported markup
     * did not have to be re-styled.
     */
    public static function variables(Website $website): array
    {
        [$primary, $secondary, $tertiary] = self::seeds($website);

        return [
            '--theme-primary' => $primary,
            '--theme-primary-hover' => self::shade($primary, -0.12),
            '--theme-primary-light' => self::tint($primary, 0.82),

            '--theme-secondary' => $secondary,
            '--theme-secondary-hover' => self::shade($secondary, -0.12),
            '--theme-secondary-light' => self::tint($secondary, 0.72),

            '--theme-tertiary' => $tertiary,
            '--theme-tertiary-hover' => self::shade($tertiary, -0.12),
            '--theme-tertiary-light' => self::tint($tertiary, 0.62),

            '--primary' => $primary,
            '--primary-foreground' => self::readable($primary),
            '--secondary' => $secondary,
            '--secondary-foreground' => self::readable($secondary),

            '--accent' => self::tint($primary, 0.93),
            '--accent-foreground' => self::shade($tertiary, -0.35),
            '--ring' => $primary,

            // The page gradient the FGE site is recognised by.
            '--page-from' => self::tint($primary, 0.95),
            '--page-to' => self::tint($primary, 0.82),
        ];
    }

    /** The same map, ready to drop into a `<style>` block. */
    public static function css(Website $website): string
    {
        $lines = [];
        foreach (self::variables($website) as $name => $value) {
            $lines[] = "  $name: $value;";
        }

        return ":root{\n" . implode("\n", $lines) . "\n}";
    }

    /** Accepts `#abc` / `#aabbcc`, rejects anything else rather than emitting broken CSS. */
    private static function hex(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        if (! preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value)) {
            return null;
        }
        if (strlen($value) === 4) {
            $value = '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
        }

        return strtolower($value);
    }

    /** Mix toward black; `$amount` is negative for darker. */
    private static function shade(string $hex, float $amount): string
    {
        return self::mix($hex, $amount < 0 ? '#000000' : '#ffffff', abs($amount));
    }

    /** Mix toward white; `$amount` of 1.0 is white. */
    private static function tint(string $hex, float $amount): string
    {
        return self::mix($hex, '#ffffff', $amount);
    }

    private static function mix(string $a, string $b, float $t): string
    {
        [$ar, $ag, $ab] = self::rgb($a);
        [$br, $bg, $bb] = self::rgb($b);
        $t = max(0.0, min(1.0, $t));

        return sprintf(
            '#%02x%02x%02x',
            (int) round($ar + ($br - $ar) * $t),
            (int) round($ag + ($bg - $ag) * $t),
            (int) round($ab + ($bb - $ab) * $t),
        );
    }

    /** Black or white, whichever stays legible on $hex. */
    private static function readable(string $hex): string
    {
        [$r, $g, $b] = self::rgb($hex);
        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;

        return $luminance > 0.6 ? '#111827' : '#ffffff';
    }

    /** @return array{int,int,int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
