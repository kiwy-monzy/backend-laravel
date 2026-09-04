<?php

use Illuminate\Support\Facades\Route;
use Modules\Storage\Http\Controllers\StorageController;

/*
| Mounted at /admin/m/storage behind session auth and `module:storage`.
| Collection writes are additionally gated per collection by `min_role`.
*/

Route::get('/', [StorageController::class, 'index'])->name('index');

// The image picker's data source, used by the content editor.
Route::get('/picker', [StorageController::class, 'picker'])->name('picker');

Route::post('/collections', [StorageController::class, 'storeCollection'])->name('collections.store');
Route::put('/collections/{collection}', [StorageController::class, 'updateCollection'])->name('collections.update');
Route::delete('/collections/{collection}', [StorageController::class, 'destroyCollection'])->name('collections.destroy');

Route::get('/{collection}', [StorageController::class, 'show'])->name('collections.show');
Route::post('/{collection}', [StorageController::class, 'store'])->name('collections.upload');
Route::get('/{collection}/{upload}/edit', [StorageController::class, 'edit'])->name('files.edit');
Route::put('/{collection}/{upload}', [StorageController::class, 'update'])->name('files.update');
Route::delete('/{collection}/{upload}', [StorageController::class, 'destroy'])->name('files.destroy');
