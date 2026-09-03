<?php

namespace App\Providers;

use App\Support\Modules;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots every module found under `modules/`.
 *
 * For each one it registers, when present:
 *
 *   routes/web.php     → `/admin/m/{slug}`, behind session auth and the
 *                        module permission gate
 *   routes/api.php     → `/api/{slug}`, behind the bearer-token middleware
 *   resources/views    → the `{slug}::` view namespace
 *   database/migrations→ picked up by `php artisan migrate`
 *   lang               → the `{slug}::` translation namespace
 *
 * **Route names are prefixed with the slug** (`crm.customers.index`), so two
 * modules can both have a `customers.index` without colliding — the failure
 * this design is guarding against, since every module is written independently.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (Modules::enabled() as $module) {
            $config = $module['path'] . '/config/config.php';
            if (is_file($config)) {
                $this->mergeConfigFrom($config, $module['slug']);
            }

            // A module may ship its own provider for bindings and observers.
            $provider = $module['provider'] ?? null;
            if ($provider && class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }

    public function boot(): void
    {
        foreach (Modules::enabled() as $module) {
            $this->bootViews($module);
            $this->bootTranslations($module);
            $this->bootMigrations($module);
            $this->bootRoutes($module);
        }
    }

    private function bootViews(array $module): void
    {
        $views = $module['path'] . '/resources/views';
        if (is_dir($views)) {
            $this->loadViewsFrom($views, $module['slug']);
        }
    }

    private function bootTranslations(array $module): void
    {
        $lang = $module['path'] . '/lang';
        if (is_dir($lang)) {
            $this->loadTranslationsFrom($lang, $module['slug']);
        }
    }

    private function bootMigrations(array $module): void
    {
        $migrations = $module['path'] . '/database/migrations';
        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }
    }

    private function bootRoutes(array $module): void
    {
        $slug = $module['slug'];

        $web = $module['path'] . '/routes/web.php';
        if (is_file($web)) {
            Route::middleware(['web', 'auth', 'module:' . $slug])
                ->prefix('admin/m/' . $slug)
                ->name($slug . '.')
                ->group($web);
        }

        $api = $module['path'] . '/routes/api.php';
        if (is_file($api)) {
            Route::middleware(['auth.api'])
                ->prefix('api/' . $slug)
                ->name('api.' . $slug . '.')
                ->group($api);
        }
    }
}
