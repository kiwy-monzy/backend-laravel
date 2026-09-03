<?php

namespace App\Support;

/**
 * The splash screens a site can show while it loads.
 *
 * All five draw from the same data — the organization's or the website's name,
 * logo and palette — so choosing one is a look, never a content decision. They
 * are pure CSS: a splash that needed JavaScript to appear would arrive after
 * the thing it is meant to cover.
 */
final class Splash
{
    public const ALL = [
        'none' => [
            'label' => 'None',
            'description' => 'Go straight to the page. The fastest thing a site can do.',
        ],
        'wordmark' => [
            'label' => 'Wordmark',
            'description' => 'The logo centred on the page background, fading out — what the React frontend did.',
        ],
        'pulse' => [
            'label' => 'Pulse',
            'description' => 'The logo over a slow pulse of the primary colour.',
        ],
        'bar' => [
            'label' => 'Progress bar',
            'description' => 'Name and tagline above a sweeping progress bar.',
        ],
        'curtain' => [
            'label' => 'Curtain',
            'description' => 'A solid brand panel that lifts away to reveal the page.',
        ],
    ];

    public static function exists(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::ALL);
    }

    public static function resolve(?string $key): string
    {
        return self::exists($key) ? $key : 'none';
    }

    public static function labels(): array
    {
        return array_map(fn (array $s) => $s['label'], self::ALL);
    }
}
