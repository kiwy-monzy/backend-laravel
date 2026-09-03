<?php

use Illuminate\Support\Facades\Route;
use Modules\Assets\Http\Controllers\AssetsController;
use Modules\Assets\Http\Controllers\AssetController;

/*
| Mounted at /admin/m/assets behind session auth and `module:assets`, which
| also refuses every write once the organization's plan has lapsed.
*/

Route::get('/', [AssetsController::class, 'index'])->name('index');

Route::get('/records', [AssetController::class, 'index'])->name('records.index');
Route::get('/records/data', [AssetController::class, 'data'])->name('records.data');
Route::get('/records/create', [AssetController::class, 'create'])->name('records.create');
Route::post('/records', [AssetController::class, 'store'])->name('records.store');
Route::get('/records/{record}/edit', [AssetController::class, 'edit'])->name('records.edit');
Route::put('/records/{record}', [AssetController::class, 'update'])->name('records.update');
Route::delete('/records/{record}', [AssetController::class, 'destroy'])->name('records.destroy');
