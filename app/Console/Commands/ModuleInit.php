<?php

namespace App\Console\Commands;

use App\Support\Modules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Scaffolds a module.
 *
 * `php artisan module:init Crm` writes the whole directory — manifest,
 * controller, model, routes, views, migration — so a new module is running
 * (with a working index page) before a line of domain code is written. With no
 * argument it initialises every module that is already on disk, which is what
 * you want after a fresh clone.
 */
class ModuleInit extends Command
{
    protected $signature = 'module:init
                            {name? : StudlyCase module name, e.g. Crm or Invoicing}
                            {--label= : Human label for the nav}
                            {--icon=box : Lucide-style icon key}
                            {--order=100 : Position in the nav}
                            {--plan= : Minimum plan required (starter|professional|enterprise)}
                            {--force : Overwrite files that already exist}';

    protected $description = 'Scaffold a new module, or register the ones already on disk';

    public function handle(): int
    {
        $name = $this->argument('name');

        if (! $name) {
            return $this->reportExisting();
        }

        $name = Str::studly($name);
        $slug = Str::kebab($name);
        $path = Modules::path($name);

        if (File::exists($path) && ! $this->option('force')) {
            $this->error("modules/$name already exists. Pass --force to overwrite its scaffold.");

            return self::FAILURE;
        }

        foreach ([
            'Http/Controllers', 'Models', 'routes', 'resources/views',
            'database/migrations', 'config',
        ] as $dir) {
            File::ensureDirectoryExists($path . '/' . $dir);
        }

        $label = $this->option('label') ?: Str::headline($name);

        $this->write($path . '/module.json', $this->manifest($name, $slug, $label));
        $this->write($path . '/routes/web.php', $this->webRoutes($name, $slug));
        $this->write($path . '/routes/api.php', $this->apiRoutes($name, $slug));
        $this->write($path . "/Http/Controllers/{$name}Controller.php", $this->controller($name, $slug));
        $this->write($path . '/resources/views/index.blade.php', $this->view($slug, $label));
        $this->write($path . '/config/config.php', $this->config($label));

        Modules::forget();

        $this->newLine();
        $this->info("Module $name created at modules/$name.");
        $this->line("  Admin:  /admin/m/$slug");
        $this->line("  API:    /api/$slug");
        $this->line('  Routes are named ' . $slug . '.* and views resolve as ' . $slug . '::*');
        $this->newLine();
        $this->line('Grant it to a role under Organization → Access, then reload the admin.');

        return self::SUCCESS;
    }

    /** With no name: list what is installed, and flag anything malformed. */
    private function reportExisting(): int
    {
        $dirs = File::isDirectory(Modules::path()) ? File::directories(Modules::path()) : [];

        if ($dirs === []) {
            $this->warn('No modules installed yet. Try: php artisan module:init Crm');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($dirs as $dir) {
            $manifest = $dir . '/module.json';
            $name = basename($dir);

            if (! File::exists($manifest)) {
                $rows[] = [$name, '—', 'no module.json', '—'];
                continue;
            }

            $data = json_decode(File::get($manifest), true);
            if (! is_array($data) || empty($data['slug'])) {
                $rows[] = [$name, '—', 'malformed module.json', '—'];
                continue;
            }

            $rows[] = [
                $name,
                $data['slug'],
                ($data['enabled'] ?? true) ? 'enabled' : 'disabled',
                $data['requires_plan'] ?? 'any plan',
            ];
        }

        $this->table(['Module', 'Slug', 'Status', 'Requires'], $rows);

        return self::SUCCESS;
    }

    private function write(string $path, string $contents): void
    {
        File::put($path, $contents);
        $this->line('  created ' . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path));
    }

    private function manifest(string $name, string $slug, string $label): string
    {
        return json_encode([
            'name' => $name,
            'slug' => $slug,
            'label' => $label,
            'description' => "$label module.",
            'icon' => $this->option('icon'),
            'order' => (int) $this->option('order'),
            'enabled' => true,
            'requires_plan' => $this->option('plan') ?: null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function webRoutes(string $name, string $slug): string
    {
        return <<<PHP
        <?php

        use Modules\\$name\\Http\\Controllers\\{$name}Controller;
        use Illuminate\\Support\\Facades\\Route;

        /*
        | Mounted at /admin/m/$slug by ModuleServiceProvider, behind session auth
        | and `module:$slug`. Route names are prefixed with `$slug.` automatically,
        | so `->name('index')` here is `$slug.index` everywhere else.
        */

        Route::get('/', [{$name}Controller::class, 'index'])->name('index');

        PHP;
    }

    private function apiRoutes(string $name, string $slug): string
    {
        return <<<PHP
        <?php

        use Modules\\$name\\Http\\Controllers\\{$name}Controller;
        use Illuminate\\Support\\Facades\\Route;

        /*
        | Mounted at /api/$slug behind bearer-token auth. Names are prefixed
        | `api.$slug.` so they cannot collide with the web routes above.
        */

        Route::get('/', [{$name}Controller::class, 'apiIndex'])->name('index');

        PHP;
    }

    private function controller(string $name, string $slug): string
    {
        return <<<PHP
        <?php

        namespace Modules\\$name\\Http\\Controllers;

        use App\\Http\\Controllers\\Web\\ModuleController;
        use Illuminate\\Http\\JsonResponse;

        class {$name}Controller extends ModuleController
        {
            protected string \$module = '$slug';

            public function index()
            {
                return view('$slug::index', [
                    'organization' => \$this->organization(),
                ]);
            }

            public function apiIndex(): JsonResponse
            {
                return response()->json(['data' => []]);
            }
        }

        PHP;
    }

    private function view(string $slug, string $label): string
    {
        return <<<BLADE
        @extends('layouts.app')
        @section('title', '$label')

        @section('content')
            <h1>{{ __('$label') }}</h1>
            <p class="sub">{{ \$organization?->name }}</p>

            <div class="card">
                <p class="dim">{{ __('This module has no pages yet.') }}</p>
                <p class="dim small">
                    {{ __('Add them in modules/:name — controllers, models, routes and views all live there.', ['name' => '$slug']) }}
                </p>
            </div>
        @endsection

        BLADE;
    }

    private function config(string $label): string
    {
        return "<?php\n\nreturn [\n    'name' => '$label',\n];\n";
    }
}
