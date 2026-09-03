<?php

use Illuminate\Support\Facades\Route;
use Modules\Zones\Http\Controllers\PlaceSearchController;
use Modules\Zones\Http\Controllers\ZoneController;
use Modules\Zones\Http\Controllers\ZonesController;

/*
| Mounted at /admin/m/zones behind session auth and `module:zones`, which also
| refuses every write once the organization's plan has lapsed.
*/

Route::get('/', [ZonesController::class, 'index'])->name('index');

Route::get('/records', [ZoneController::class, 'index'])->name('records.index');
Route::get('/records/create', [ZoneController::class, 'create'])->name('records.create');
Route::post('/records', [ZoneController::class, 'store'])->name('records.store');
Route::get('/records/{record}/edit', [ZoneController::class, 'edit'])->name('records.edit');
Route::put('/records/{record}', [ZoneController::class, 'update'])->name('records.update');
Route::delete('/records/{record}', [ZoneController::class, 'destroy'])->name('records.destroy');

// The other zones, drawn behind the one being edited so areas are not overlapped by accident.
Route::get('/records/{id}/neighbours', [ZoneController::class, 'neighbours'])->name('records.neighbours');

// Place search, proxied so Nominatim's usage policy can actually be honoured.
Route::get('/places', [PlaceSearchController::class, 'search'])->name('places.search');

// Attaching zones to a record of any zoned kind; the target module's own
// permission is what is checked, not this one's.
Route::put('/attach/{kind}/{record}', [\Modules\Zones\Http\Controllers\ZoneAttachController::class, 'update'])->name('attach');
