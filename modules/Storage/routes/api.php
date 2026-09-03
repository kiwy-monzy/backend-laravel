<?php

use Illuminate\Support\Facades\Route;
use Modules\Storage\Http\Controllers\StorageApiController;

/*
| Mounted at /api/storage behind bearer-token auth, named `api.storage.*`.
*/

Route::get('/collections', [StorageApiController::class, 'collections'])->name('collections');
Route::get('/files', [StorageApiController::class, 'files'])->name('files');
Route::get('/usage', [StorageApiController::class, 'usage'])->name('usage');
