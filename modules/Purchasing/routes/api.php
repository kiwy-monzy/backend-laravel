<?php

use Illuminate\Support\Facades\Route;
use Modules\Purchasing\Http\Controllers\PurchasingApiController;

/*
| Mounted at /api/purchasing behind bearer-token auth, named `api.purchasing.*`.
*/

Route::get('/records', [PurchasingApiController::class, 'index'])->name('records.index');
Route::get('/records/{record}', [PurchasingApiController::class, 'show'])->name('records.show');
