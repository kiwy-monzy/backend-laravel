<?php

use App\Http\Controllers\Web\BackupController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\ExportController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\MailController;
use App\Http\Controllers\Web\OrganizationController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\SiteController;
use App\Http\Controllers\Web\SystemController;
use App\Http\Controllers\Web\UploadController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|---------------------------------------------------------------------------
| Admin
|---------------------------------------------------------------------------
|
| Everything an operator touches lives under /admin. The JSON API in
| routes/api.php is mounted at the root with no prefix (that is what the old
| Rust server exposed and what the packaged frontend still calls), so keeping
| the HTML pages in their own segment is what stops the two colliding.
|
*/
Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'show'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('auth')->group(function () {
        // Chrome preferences are session-only, so they must work for every
        // signed-in user including the ones with no admin access.
        Route::post('/settings/theme', [SettingsController::class, 'theme'])->name('settings.theme');
        Route::post('/settings/locale', [SettingsController::class, 'locale'])->name('settings.locale');
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');

        Route::middleware('can:admin-area')->group(function () {
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
            Route::get('/search', [SearchController::class, 'index'])->name('search');

            // Renders your own site in a template it has not adopted. Behind
            // auth because it exposes the site as it would look, including any
            // sections you have not published yet.
            Route::get('/preview/{template}', [SiteController::class, 'preview'])->name('templates.preview');

            // The installation, seen from above. System admins only — this is
            // where organizations are created and modules granted, which is
            // precisely what an organization must not decide for itself.
            Route::get('/system', [SystemController::class, 'index'])->name('system.index');
            Route::get('/system/users', [SystemController::class, 'users'])->name('system.users');
            Route::post('/system/users', [SystemController::class, 'storeUser'])->name('system.users.store');
            Route::put('/system/users/{user}', [SystemController::class, 'updateUser'])->name('system.users.update');
            Route::delete('/system/users/{user}', [SystemController::class, 'destroyUser'])->name('system.users.destroy');
            Route::get('/system/organizations/create', [SystemController::class, 'createOrganization'])->name('system.organization.create');
            Route::post('/system/organizations', [SystemController::class, 'storeOrganization'])->name('system.organization.store');
            Route::get('/system/organizations/{organization}', [SystemController::class, 'organization'])->name('system.organization');
            Route::put('/system/organizations/{organization}', [SystemController::class, 'updateOrganization'])->name('system.organization.update');
            Route::put('/system/organizations/{organization}/modules', [SystemController::class, 'updateModules'])->name('system.organization.modules');
            Route::put('/system/organizations/{organization}/presentation', [SystemController::class, 'updatePresentation'])->name('system.organization.presentation');
            Route::get('/system/organizations/{organization}/websites/create', [SystemController::class, 'createWebsite'])->name('system.organization.website.create');
            Route::post('/system/organizations/{organization}/websites', [SystemController::class, 'storeWebsite'])->name('system.organization.website.store');

            // The organization and the things modules gate on. Core rather
            // than a module: a module that could revoke its own gate would
            // not be a gate.
            // Typeahead lookups for the form pickers. One endpoint, gated per
            // source by the module the source belongs to.
            Route::get('/lookup/{source}', \App\Http\Controllers\Web\LookupController::class)
                ->name('lookup');

            // Portraits, for the signed-in user and for the team. Both store
            // into the organization's own `avatars` collection.
            Route::post('/settings/avatar', [\App\Http\Controllers\Web\AvatarController::class, 'updateMine'])
                ->name('settings.avatar');
            Route::post('/organization/team/{member}/avatar', [\App\Http\Controllers\Web\AvatarController::class, 'updateMember'])
                ->name('organization.team.avatar');
            Route::delete('/organization/team/{member}/avatar', [\App\Http\Controllers\Web\AvatarController::class, 'removeMember'])
                ->name('organization.team.avatar.remove');

            // How references are shaped. The numbers allocate themselves; the
            // pattern is the organization's standing decision, so it is gated
            // to owners and organization admins inside the controller.
            Route::get('/numbering', [\App\Http\Controllers\Web\NumberingController::class, 'edit'])->name('numbering.edit');
            Route::put('/numbering', [\App\Http\Controllers\Web\NumberingController::class, 'update'])->name('numbering.update');

            Route::get('/organization', [OrganizationController::class, 'edit'])->name('organization.edit');
            Route::put('/organization', [OrganizationController::class, 'update'])->name('organization.update');
            Route::post('/organization/switch', [OrganizationController::class, 'switch'])->name('organization.switch');
            Route::get('/organization/team', [OrganizationController::class, 'team'])->name('organization.team');
            Route::post('/organization/team', [OrganizationController::class, 'addMember'])->name('organization.team.add');
            Route::put('/organization/team/{member}', [OrganizationController::class, 'updateMember'])->name('organization.team.update');
            Route::delete('/organization/team/{member}', [OrganizationController::class, 'removeMember'])->name('organization.team.remove');
            Route::get('/organization/access', [OrganizationController::class, 'access'])->name('organization.access');
            Route::put('/organization/access', [OrganizationController::class, 'updateAccess'])->name('organization.access.update');
            Route::get('/organization/subscription', [OrganizationController::class, 'subscription'])->name('organization.subscription');
            Route::put('/organization/subscription', [OrganizationController::class, 'updateSubscription'])->name('organization.subscription.update');

            Route::get('/storage', [UploadController::class, 'index'])->name('uploads.index');
            Route::post('/storage', [UploadController::class, 'store'])->name('uploads.store');
            Route::delete('/storage/{upload}', [UploadController::class, 'destroy'])->name('uploads.destroy');

            Route::get('/mail', [MailController::class, 'index'])->name('mail.index');
            Route::post('/mail', [MailController::class, 'save'])->name('mail.save');

            // Export and backup — the two ways data leaves. Both gated: export
            // by the `export` action, backup to admins and managers.
            Route::get('/export', [ExportController::class, 'index'])->name('export.index');
            Route::get('/export/{source}', [ExportController::class, 'run'])->name('export.run');

            Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
            Route::post('/backup', [BackupController::class, 'download'])->name('backup.download');

            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        });
    });
});

/*
|---------------------------------------------------------------------------
| Public sites
|---------------------------------------------------------------------------
|
| `/` resolves by hostname, falling back to the first active site, so a single
| deployment can serve every website. `/s/{site}` addresses one by slug, which
| is what the admin's preview link and local development use.
|
*/
// Email verification and password-reset landing links. Public by design — the
// token in the URL is the proof — so they sit outside the auth group. They are
// served by the Contact module's controller when it is installed.
if (class_exists(\Modules\Contact\Http\Controllers\ContactController::class)) {
    Route::get('/verify/{token}', [\Modules\Contact\Http\Controllers\ContactController::class, 'verify'])->name('contact.verify');
    Route::get('/reset/{token}', [\Modules\Contact\Http\Controllers\ContactController::class, 'showReset'])->name('contact.reset.show');
    Route::post('/reset/{token}', [\Modules\Contact\Http\Controllers\ContactController::class, 'updateReset'])->name('contact.reset.update');
}

// Legacy uploads fallback – old content used `/uploads/<file>` while current
// storage lives at `storage/app/public/uploads/…` served as `/storage/...`.
// On cPanel the `public/storage` symlink is often missing, and many seeded
// rows still point at the legacy prefix, so serve from disk here rather than
// 404. Search by basename across organization folders and `_shared`.
Route::get('/uploads/{path}', function (string $path) {
    $path = ltrim($path, '/');
    $disk = Storage::disk('public');

    // Direct match first (e.g. /uploads/1767...jpeg when file sits flat)
    if ($disk->exists('uploads/'.$path)) {
        return $disk->response('uploads/'.$path);
    }
    // Already org-scoped? Try as-is
    if ($disk->exists($path)) {
        return $disk->response($path);
    }

    $basename = basename($path);

    // Search across existing uploads
    foreach ($disk->allFiles('uploads') as $file) {
        if (basename($file) === $basename) {
            return $disk->response($file);
        }
    }

    // Last resort: check bundled fixture assets
    $bundled = database_path('seeders/fixtures/assets/website/'.$basename);
    if (is_file($bundled)) {
        return response()->file($bundled);
    }

    abort(404);
})->where('path', '.*');

// Also serve /storage/* via Laravel when the symlink is missing (common on cPanel)
Route::get('/storage/{path}', function (string $path) {
    $disk = Storage::disk('public');
    if ($disk->exists($path)) {
        return $disk->response($path);
    }
    // Fall back to basename search across org folders
    $basename = basename($path);
    foreach ($disk->allFiles('uploads') as $file) {
        if (basename($file) === $basename) {
            return $disk->response($file);
        }
    }
    $bundled = database_path('seeders/fixtures/assets/website/'.$basename);
    if (is_file($bundled)) {
        return response()->file($bundled);
    }
    abort(404);
})->where('path', '.*');

// The host's own site, unprefixed. This is the canonical form: on fge.or.tz the
// pages are /about and /donate, and on localhost they are the same paths, since
// `root()` falls back to FGE when no domain matches. Registered before the slug
// group so the plain paths win, and after /admin so nothing above is shadowed.
Route::get('/', [SiteController::class, 'root'])->name('site.root');
Route::get('/about', [SiteController::class, 'hostPage'])->defaults('page', 'about')->name('site.host.about');
Route::get('/projects', [SiteController::class, 'hostPage'])->defaults('page', 'projects')->name('site.host.projects');
Route::get('/gallery', [SiteController::class, 'hostPage'])->defaults('page', 'gallery')->name('site.host.gallery');
Route::get('/blog', [SiteController::class, 'hostPage'])->defaults('page', 'blog')->name('site.host.blog');
Route::get('/blog/{post}', [SiteController::class, 'hostPost'])->name('site.host.post');
Route::get('/events', [SiteController::class, 'hostPage'])->defaults('page', 'events')->name('site.host.events');
Route::get('/events/{event}', [SiteController::class, 'hostEvent'])->name('site.host.event');
Route::get('/team', [SiteController::class, 'hostPage'])->defaults('page', 'team')->name('site.host.team');
Route::get('/donate', [SiteController::class, 'hostPage'])->defaults('page', 'donate')->name('site.host.donate');
Route::get('/contact', [SiteController::class, 'hostPage'])->defaults('page', 'contact')->name('site.host.contact');
Route::post('/contact', [SiteController::class, 'hostContact'])->name('site.host.contact.send');

// Any site by slug. Still the only way to reach a tenant this hostname is not
// serving, which is what the admin's preview link does.
Route::prefix('s/{site}')->group(function () {
    Route::get('/', [SiteController::class, 'home'])->name('site.home');
    Route::get('/about', [SiteController::class, 'page'])->defaults('page', 'about')->name('site.about');
    Route::get('/projects', [SiteController::class, 'page'])->defaults('page', 'projects')->name('site.projects');
    Route::get('/gallery', [SiteController::class, 'page'])->defaults('page', 'gallery')->name('site.gallery');
    Route::get('/blog', [SiteController::class, 'page'])->defaults('page', 'blog')->name('site.blog');
    Route::get('/blog/{post}', [SiteController::class, 'post'])->name('site.post');
    Route::get('/events', [SiteController::class, 'page'])->defaults('page', 'events')->name('site.events');
    Route::get('/events/{event}', [SiteController::class, 'event'])->name('site.event');
    Route::get('/team', [SiteController::class, 'page'])->defaults('page', 'team')->name('site.team');
    Route::get('/donate', [SiteController::class, 'page'])->defaults('page', 'donate')->name('site.donate');
    Route::get('/contact', [SiteController::class, 'page'])->defaults('page', 'contact')->name('site.contact');
    Route::post('/contact', [SiteController::class, 'contact'])->name('site.contact.send');
});
