<?php

use Illuminate\Support\Facades\Route;
use Modules\Cart\Http\Controllers\CartApiController;

/*
| Mounted at /api/cart behind bearer-token auth, named `api.cart.*`.
*/

Route::get('/records', [CartApiController::class, 'index'])->name('records.index');
Route::get('/records/{record}', [CartApiController::class, 'show'])->name('records.show');
