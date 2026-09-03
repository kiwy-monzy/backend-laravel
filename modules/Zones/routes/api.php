<?php

use Illuminate\Support\Facades\Route;
use Modules\Zones\Http\Controllers\ZonesApiController;

/*
| Mounted at /api/zones behind bearer-token auth, named `api.zones.*`.
*/

Route::get('/', [ZonesApiController::class, 'index'])->name('index');
Route::get('/resolve', [ZonesApiController::class, 'resolve'])->name('resolve');
Route::get('/{zone}', [ZonesApiController::class, 'show'])->name('show');
