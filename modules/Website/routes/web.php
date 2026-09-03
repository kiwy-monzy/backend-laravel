<?php

use Illuminate\Support\Facades\Route;
use Modules\Website\Http\Controllers\ContentController;
use Modules\Website\Http\Controllers\DonationController;
use Modules\Website\Http\Controllers\GalleryController;
use Modules\Website\Http\Controllers\MessageController;
use Modules\Website\Http\Controllers\OverviewController;
use Modules\Website\Http\Controllers\VolunteerController;
use Modules\Website\Http\Controllers\WebsiteController;

/*
| Everything the public site is made of, mounted at /admin/m/website behind
| session auth and `module:website`.
|
| Route names keep their old short form (`content.edit`, `gallery.index`) via
| the module's `website.` prefix — so they read as `website.content.edit`.
| Anything that linked to the old names is updated alongside this file.
*/

Route::get('/', [OverviewController::class, 'index'])->name('index');

Route::get('/content', [ContentController::class, 'index'])->name('content.index');
Route::get('/content/{section}', [ContentController::class, 'edit'])->name('content.edit');
Route::put('/content/{section}', [ContentController::class, 'update'])->name('content.update');

Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery.index');
Route::get('/gallery/data', [GalleryController::class, 'data'])->name('gallery.data');
Route::post('/gallery', [GalleryController::class, 'store'])->name('gallery.store');
Route::put('/gallery/{image}', [GalleryController::class, 'update'])->name('gallery.update');
Route::delete('/gallery/{image}', [GalleryController::class, 'destroy'])->name('gallery.destroy');

Route::get('/donations', [DonationController::class, 'index'])->name('donations.index');
Route::put('/donations/{donation}', [DonationController::class, 'update'])->name('donations.update');
Route::delete('/donations/{donation}', [DonationController::class, 'destroy'])->name('donations.destroy');

Route::get('/volunteers', [VolunteerController::class, 'index'])->name('volunteers.index');
Route::put('/volunteers/{volunteer}', [VolunteerController::class, 'update'])->name('volunteers.update');
Route::delete('/volunteers/{volunteer}', [VolunteerController::class, 'destroy'])->name('volunteers.destroy');

Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
Route::put('/messages/{message}', [MessageController::class, 'update'])->name('messages.update');
Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');

Route::get('/sites', [WebsiteController::class, 'index'])->name('sites.index');
// `create` before `{website}` — otherwise the wildcard swallows it and the
// "Add website" link 404s looking for a site whose id is literally "create".
Route::get('/sites/create', [WebsiteController::class, 'create'])->name('sites.create');
Route::post('/sites', [WebsiteController::class, 'store'])->name('sites.store');
Route::get('/sites/{website}/edit', [WebsiteController::class, 'edit'])->name('sites.edit');
Route::get('/sites/{website}', [OverviewController::class, 'show'])->name('sites.show');
Route::put('/sites/{website}', [WebsiteController::class, 'update'])->name('sites.update');
Route::delete('/sites/{website}', [WebsiteController::class, 'destroy'])->name('sites.destroy');
Route::post('/sites/switch', [OverviewController::class, 'switch'])->name('sites.switch');
