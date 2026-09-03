<?php

use Illuminate\Support\Facades\Route;
use Modules\ServiceHub\Http\Controllers\ServiceHubApiController;

/*
| Mounted at /api/servicehub behind bearer-token auth, named `api.servicehub.*`.
|
| Read-only: the customer and provider apps browse through this, and every
| change still goes through the admin routes and their permission checks.
*/

Route::get('/providers', [ServiceHubApiController::class, 'providers'])->name('providers');
Route::get('/services', [ServiceHubApiController::class, 'services'])->name('services');
Route::get('/requests', [ServiceHubApiController::class, 'requests'])->name('requests');
Route::get('/bookings', [ServiceHubApiController::class, 'bookings'])->name('bookings');
Route::get('/bookings/{record}', [ServiceHubApiController::class, 'booking'])->name('bookings.show');
