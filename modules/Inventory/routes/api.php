<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\InventoryApiController;

/*
| Mounted at /api/inventory behind bearer-token auth, named `api.inventory.*`.
*/

Route::get('/records', [InventoryApiController::class, 'index'])->name('records.index');
Route::get('/records/{record}', [InventoryApiController::class, 'show'])->name('records.show');
