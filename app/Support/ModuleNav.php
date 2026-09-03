<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * The second rail: a module's own sections.
 *
 * **A module is more than one page.** Website has content, gallery, donations,
 * volunteers, messages and sites; Storage has a collection per folder. Putting
 * all of those in the main rail made it thirty items long and buried the
 * modules among them, so the rail lists modules and this lists what is inside
 * whichever one you are in.
 *
 * Sections come from `module.json` — a module declares its own, and one that
 * declares none simply has no sub-rail. Routes are checked with `Route::has()`
 * before being offered, because a mistyped name in a manifest should mean a
 * missing tab rather than a 500 on every page in the module.
 */
final class ModuleNav
{
    /**
     * The sections of the module the current request is inside, or [].
     *
     * @return array<int,array{label:string,href:string,on:bool}>
     */
    public static function sections(?User $user): array
    {
        $slug = self::activeModule();

        if (! $slug || ! $user) {
            return [];
        }

        $module = Modules::find($slug);
        if (! $module || ! $user->allowedModule($slug)) {
            return [];
        }

        $sections = [];

        foreach ($module['sections'] ?? [] as $section) {
            $name = $section['route'] ?? null;
            if (! $name || ! Route::has($name)) {
                continue;
            }

            // A section can name the role it needs — Website's "Sites" is an
            // owner's page, and showing it to an employee only to refuse them
            // is worse than not showing it.
            if (($section['owner_only'] ?? false) && ! $user->isOwner()) {
                continue;
            }

            // The installation pages belong to whoever runs the installation.
            // `isOwner()` is true for an organization's owner as well, so the
            // System tab needs the narrower test or every owner would see a
            // tab that answers them with 403.
            if (($section['system_admin_only'] ?? false) && ! $user->isSystemAdmin()) {
                continue;
            }

            // A section can be withheld on its own, so someone may hold one tab
            // of a module without the rest of it.
            if (! $user->allowedSection($slug, self::key($section))) {
                continue;
            }

            $sections[] = [
                'label' => __($section['label']),
                'href' => route($name, $section['params'] ?? []),
                'on' => self::isOn($section, $name),
            ];
        }

        return $sections;
    }

    /**
     * The stable name a section is granted by.
     *
     * The route name, not the label: labels are translated and edited, and a
     * permission that changed meaning when someone reworded a tab would be a
     * permission nobody could rely on.
     */
    public static function key(array $section): string
    {
        return $section['route'] ?? ($section['label'] ?? '');
    }

    /**
     * Every grantable section of a module, as `key => label`.
     *
     * Used by the access screen to list what can be handed out.
     *
     * @return array<string,string>
     */
    public static function grantable(string $slug): array
    {
        $module = Modules::find($slug);
        $out = [];

        foreach ($module['sections'] ?? [] as $section) {
            $key = self::key($section);

            if ($key !== '') {
                $out[$key] = __($section['label'] ?? $key);
            }
        }

        return $out;
    }

    /**
     * Is this the section the request is on?
     *
     * Route name alone is not enough once several sections share one route and
     * differ only by a parameter — Quotes, Invoices and Receipts are all the
     * document list at `?type=…`. So when a section declares `params`, every
     * one of them must match the request too, and a section with no `type` only
     * lights up when the request carries the controller's default.
     */
    private static function isOn(array $section, string $name): bool
    {
        if (! request()->routeIs($section['active'] ?? $name)) {
            return false;
        }

        foreach ($section['params'] ?? [] as $key => $value) {
            $actual = request()->input($key);

            // No value on the request means the controller is using its own
            // fallback, so only the section marked `default` lights up — which
            // is why visiting /invoices bare highlights Invoices, not Quotes.
            if ($actual === null) {
                if (! ($section['default'] ?? false)) {
                    return false;
                }

                continue;
            }

            if ((string) $actual !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Core pages that belong to the Admin module's sub-rail.
     *
     * The organization, team, access, plan and user pages are core rather than
     * a module — a module that could revoke its own gate would not be a gate —
     * but they are what Administration *is*, so the sub-rail treats them as
     * part of it. Without this the rail would vanish the moment you opened one.
     */
    private const ADMIN_PREFIXES = ['organization', 'system', 'users', 'numbering'];

    /** The module slug the current route belongs to, if any. */
    public static function activeModule(): ?string
    {
        $name = request()->route()?->getName();

        if (! $name) {
            return null;
        }

        $slug = explode('.', $name)[0];

        if (in_array($slug, self::ADMIN_PREFIXES, true) && Modules::exists('admin')) {
            return 'admin';
        }

        return Modules::exists($slug) ? $slug : null;
    }

    public static function activeLabel(): ?string
    {
        $slug = self::activeModule();

        return $slug ? Modules::label($slug) : null;
    }
}
