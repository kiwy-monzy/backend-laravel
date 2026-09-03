<?php

namespace App\Providers;

use App\Support\Taxonomy;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Makes the taxonomies available everywhere without each view loading them.
 *
 * Registered as a singleton and shared to Blade lazily — the maps are only
 * parsed the first time a form actually asks for them, and cached after, so a
 * page that shows no classifier pays nothing.
 */
class TaxonomyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Taxonomy::class, fn () => new Taxonomy);
    }

    public function boot(): void
    {
        // A Blade directive so a form reads `@taxonomyJson('google_product')`
        // rather than the fully-qualified static call.
        Blade::directive('taxonomyJson', function ($expression) {
            return "<?php echo json_encode(\\App\\Support\\Taxonomy::options($expression)); ?>";
        });
    }
}
