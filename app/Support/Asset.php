<?php

namespace App\Support;

/**
 * Cache-busted asset URLs.
 *
 * **Because there is no build step, there are no content hashes in filenames.**
 * `app.css` is `app.css` forever, so a browser that cached it yesterday keeps
 * using yesterday's stylesheet after a deploy — which on a VPS means "the fix
 * went out and the users cannot see it", the least debuggable class of bug.
 * Stamping the file's modification time on the query string is the cheapest
 * fix that actually works: it changes exactly when the file changes.
 */
final class Asset
{
    public static function v(string $path): string
    {
        $full = public_path($path);
        $stamp = is_file($full) ? filemtime($full) : null;

        return asset($path) . ($stamp ? '?v=' . $stamp : '');
    }
}
