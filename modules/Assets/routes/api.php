<?php

use Illuminate\Support\Facades\Route;
use Modules\Assets\Http\Controllers\AssetsApiController;

/*
| Mounted at /api/assets behind bearer-token auth, named `api.assets.*`.
*/

Route::get('/records', [AssetsApiController::class, 'index'])->name('records.index');
Route::get('/records/{record}', [AssetsApiController::class, 'show'])->name('records.show');
