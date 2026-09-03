<?php

use Illuminate\Support\Facades\Route;
use Modules\Bookings\Http\Controllers\BookingsApiController;

/*
| Mounted at /api/bookings behind bearer-token auth, named `api.bookings.*`.
*/

Route::get('/records', [BookingsApiController::class, 'index'])->name('records.index');
Route::get('/records/{record}', [BookingsApiController::class, 'show'])->name('records.show');
