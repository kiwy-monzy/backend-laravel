<?php

namespace App\Providers;

use App\Models\Message;
use App\Models\User;
use App\Models\Website;
use App\Support\Bootstrap;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->honourTheProxyPrefix();

        // The admin area is the whole gate: individual pages narrow further by
        // website, but nobody without a role reaches any of them.
        Gate::define('admin-area', fn (User $user) => $user->hasAdminAccess());

        // The chrome's stylesheet already has rules for `ul.pagination`, which
        // is the markup this preset emits; the Tailwind default would have
        // rendered unstyled in a project with no Tailwind build.
        Paginator::useBootstrapFour();

        // Users, organizations and starting content all come from one place —
        // see App\Support\Bootstrap. It is a no-op once the installation exists.
        Bootstrap::runIfNeeded();

        $this->shareChrome();
    }

    /**
     * Generate links under the path the app is published at.
     *
     * When nginx mounts this app at `/laravel/` it strips that prefix before
     * handing the request over, so Laravel sees `/admin/login` and builds its
     * links without the prefix — every one of them a 404 for the browser,
     * because the app answers at `/laravel/admin/login`.
     *
     * `APP_URL` already records where the app really lives, so forcing the URL
     * root to it fixes redirects, form actions and asset links in one place.
     * Off unless `APP_URL_FORCE_ROOT` is set, since an app served at a domain
     * root needs none of this and would only inherit a stale APP_URL. Read via
     * config, not env(): a cached config makes every env() call here null, and
     * a cached config is precisely what production runs.
     */
    private function honourTheProxyPrefix(): void
    {
        if (! config('app.force_root_url') || ! ($url = config('app.url'))) {
            return;
        }

        \Illuminate\Support\Facades\URL::forceRootUrl($url);

        if (str_starts_with($url, 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }

    /**
     * Everything `layouts/app.blade.php` needs on every page.
     *
     * A composer rather than a base controller: the layout is rendered by
     * redirects-with-flash and error pages too, which never reach a controller
     * of ours, and a missing `$currentWebsite` there would be a 500 on the way
     * to showing an error.
     */
    private function shareChrome(): void
    {
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();

            $view->with([
                'appTheme' => session('chrome.theme', 'light'),
                'appFontSize' => session('chrome.font', 'normal'),
                'appLocale' => app()->getLocale(),
                'currentWebsite' => $this->currentWebsite($user),
                'ownerWebsites' => $user?->isOwner() ? Website::orderBy('name')->get() : collect(),
                'myOrganizations' => $this->myOrganizations($user),
                'navUnreadMessages' => $this->unreadCount($user),
            ]);
        });
    }

    /**
     * The site being edited: an owner's chosen one, or the user's own.
     *
     * Falls back to the user's home site when the session points at a website
     * that has since been deleted, so a stale session cannot strand anyone.
     */
    private function currentWebsite(?User $user): ?Website
    {
        if (! $user) {
            return null;
        }

        if ($user->isOwner()) {
            $chosen = session('chrome.website_id');
            if ($chosen && $site = Website::find($chosen)) {
                return $site;
            }
        }

        return $user->website ?? Website::orderBy('created_at')->first();
    }

    /**
     * The organizations this user may switch between: every one they hold an
     * active seat on, plus their current one. The system administrator is
     * seated on each agency it set up (TANROADS, TARURA, MoW-Buildings) as well
     * as its own Knowlia, so this is how it moves between the tenants it runs.
     */
    private function myOrganizations(?User $user)
    {
        if (! $user) {
            return collect();
        }

        try {
            $ids = \App\Models\OrganizationMember::where('user_id', $user->id)
                ->where('active', true)
                ->pluck('organization_id')
                ->push($user->organization_id)
                ->filter()
                ->unique();

            return \App\Models\Organization::whereIn('id', $ids)->orderBy('name')->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    private function unreadCount(?User $user): int
    {
        if (! $user?->hasAdminAccess()) {
            return 0;
        }

        try {
            $site = $this->currentWebsite($user);

            return Message::where('is_read', false)
                ->when($site && ! $user->isOwner(), fn ($q) => $q->where('website_id', $site->id))
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
