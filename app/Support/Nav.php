<?php

namespace App\Support;

use App\Models\User;

/**
 * The admin sidebar's tab list.
 *
 * **The app owns the tabs, the chrome only styles them.** A new tab is one
 * edit here rather than a change in two places that have to agree. It is PHP
 * because the roles have to be checked server-side anyway — a plain user must
 * not receive the Users entry at all, not merely have it hidden with CSS.
 *
 * Icons are raw lucide-style SVG so the markup carries no icon-font dependency.
 */
final class Nav
{
    public const ICON = [
        'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>',
        'websites' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
        'content' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        'gallery' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
        'storage' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7.5V6a2 2 0 0 1 2-2h4l2 2.5h6a2 2 0 0 1 2 2v1"/><path d="M3 9h18v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>',
        'donations' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
        'volunteers' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>',
        'messages' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
        'mail' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="22 6 12 13 2 6"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>',
        'preview' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        'login' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>',
        'organization' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l8-4 8 4v14"/><path d="M17 21v-8.5a2.5 2.5 0 0 0-5 0V21"/></svg>',
        'system' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>',
        'website' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M3.6 9h16.8M3.6 15h16.8"/><path d="M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18z"/></svg>',
        'crm' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="3.5"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.3a4 4 0 0 1 0 7.4"/></svg>',
        'invoicing' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 3h11l4 4v13a1 1 0 0 1-1.5.9L16 20l-2.5 1.4L11 20l-2.5 1.4L6 20l-1.5.9A1 1 0 0 1 3 20V5a2 2 0 0 1 2-2z"/><path d="M8 9h7M8 13h5"/></svg>',
        'expenses' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2v20"/><path d="M17 6.5C17 4.6 14.8 3.5 12 3.5S7 4.6 7 6.5s2.2 2.8 5 3.4 5 1.5 5 3.6-2.2 3-5 3-5-1.1-5-3"/></svg>',
        'inventory' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 16V8l-9-5-9 5v8l9 5 9-5z"/><path d="M3.3 7.5 12 12.4l8.7-4.9M12 12.4V21"/></svg>',
        'projects' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/><path d="M8 13h4M8 16h7"/></svg>',
        'purchasing' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 4h2l2.4 11.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6"/><circle cx="10" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/></svg>',
        'fulfillment' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17.5" cy="18" r="1.6"/></svg>',
        'billing' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 3v5h-5"/><path d="M12 8v4l2.5 1.5"/></svg>',
        'bookings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/><path d="M9 14.5l2 2 4-4"/></svg>',
        'procurement' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/></svg>',
        'accounting' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 10h3M13 10h3M8 14h3M13 14h3M8 18h8"/></svg>',
        'departments' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="9" y="2" width="6" height="5" rx="1"/><rect x="2" y="16" width="6" height="5" rx="1"/><rect x="16" y="16" width="6" height="5" rx="1"/><path d="M12 7v5M5 16v-2h14v2"/></svg>',
        'contact' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
        'admin' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2 4 5v6c0 5 3.4 9 8 11 4.6-2 8-6 8-11V5z"/><path d="m9 12 2 2 4-4"/></svg>',
        'reports' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="4" width="3" height="14"/></svg>',
        'assets' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="7" width="20" height="12" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M12 12v3"/></svg>',
        'tickets' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 6v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-6z"/><path d="M13 5v14"/></svg>',
        'cart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.6 12.1a2 2 0 0 0 2 1.6h8.1a2 2 0 0 0 2-1.6L22 7H6"/></svg>',
        'workerly' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>',
        'contracts' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 17c1.5-3 3-3 4 0s2.5 3 4 0"/></svg>',
        'ipc' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-4"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M8 11l2 2 3-4"/><line x1="16" y1="16" x2="8" y2="16"/></svg>',
        'servicehub' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14.7 6.3a4 4 0 0 1-5.4 5.4L4 17v3h3l5.3-5.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.5-.5-.5-2.5z"/><path d="M16 16l4 4"/></svg>',
        'zones' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 8.5 9 5l6 3.5L21 5v10.5L15 19l-6-3.5L3 19z"/><circle cx="12" cy="11" r="2.2"/></svg>',
        'map' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="1 6 8 3 16 6 23 3 23 18 16 21 8 18 1 21 1 6"/><line x1="8" y1="3" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="21"/></svg>',
        'export' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
        'backup' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"/><path d="m9 16 2 2 4-4"/></svg>',
        // The fallback for a module whose manifest names an icon we do not ship.
        'module' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
        'box' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>',
        'invoice' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
    ];

    /**
     * The main rail, filtered to what this user may actually reach.
     *
     * @return array<int,array{id:string,label:string,href:string,icon:string,on:bool}>
     */
    public static function sections(?User $user): array
    {
        if (! $user) {
            return [self::item('login', __('Sign in'), route('login'), 'login')];
        }

        if (! $user->hasAdminAccess()) {
            // A plain user gets their profile and nothing else. The admin pages
            // are not merely hidden from them — the routes reject them too.
            return [self::item('settings', __('My profile'), route('settings.edit'), 'settings.*')];
        }

        $items = [self::item('dashboard', __('Dashboard'), route('dashboard'), 'dashboard')];

        // **The rail lists modules, not pages.** Everything the website admin
        // used to occupy here — content, gallery, donations, messages — is now
        // inside the Website module and reached through the sub-rail, which is
        // what keeps this list short enough to scan.
        foreach (self::modulesFor($user) as $module) {
            $items[] = self::item(
                $module['slug'],
                __($module['label']),
                route($module['slug'] . '.index'),
                $module['slug'] . '.*',
                $module['icon'] ?? 'module',
            );
        }

        return $items;
    }

    /**
     * The enabled modules this user may open, in registry order.
     *
     * Returns nothing rather than throwing when the user has no organization
     * yet: the nav renders on every page including the ones that would let
     * someone fix that.
     */
    private static function modulesFor(?User $user): array
    {
        $organization = $user?->organization;

        if (! $organization) {
            return [];
        }

        $role = $user->orgRole();
        $allowed = [];

        foreach (\App\Support\Modules::enabled() as $slug => $module) {
            // A module whose routes failed to register would 500 the whole
            // sidebar on `route()`, taking every page with it.
            if ($organization->allowsModule($role, $slug) && \Illuminate\Support\Facades\Route::has($slug . '.index')) {
                $allowed[] = $module;
            }
        }

        return $allowed;
    }

    /** The footer rail: the things that are not places in the site's data. */
    public static function footer(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $items = [];

        if ($user->hasAdminAccess() && $user->website) {
            $items[] = self::item(
                'preview',
                __('View site'),
                site_url($user->website, 'home'),
                'site.*'
            );
        }

        if ($user->isSystemAdmin()) {
            $items[] = self::item('system', __('System'), route('system.index'), 'system.*');
        }

        // Export needs the `export` action; backup is admins and managers only.
        if ($user->canInModule('export')) {
            $items[] = self::item('export', __('Export'), route('export.index'), 'export.*');
        }

        if (in_array($user->orgRole(), ['admin', 'manager'], true)) {
            $items[] = self::item('backup', __('Backup'), route('backup.index'), 'backup.*');
        }

        if ($user->organization) {
            $items[] = self::item('organization', __('Organization'), route('organization.edit'), 'organization.*');
        }

        if ($user->hasAdminAccess()) {
            $items[] = self::item('settings', __('Settings'), route('settings.edit'), 'settings.*');
        }

        return $items;
    }

    private static function item(string $id, string $label, string $href, string $pattern, ?string $icon = null): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'href' => $href,
            'icon' => self::ICON[$icon ?? $id] ?? self::ICON['module'],
            'on' => request()->routeIs($pattern),
        ];
    }
}
