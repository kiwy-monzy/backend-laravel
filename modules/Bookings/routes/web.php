<?php

use Illuminate\Support\Facades\Route;
use Modules\Bookings\Http\Controllers\BookingsController;
use Modules\Bookings\Http\Controllers\AppointmentController;

/*
| Mounted at /admin/m/bookings behind session auth and `module:bookings`, which
| also refuses every write once the organization's plan has lapsed.
*/

Route::get('/', [BookingsController::class, 'index'])->name('index');

Route::get('/records', [AppointmentController::class, 'index'])->name('records.index');
Route::get('/records/data', [AppointmentController::class, 'data'])->name('records.data');
Route::get('/records/create', [AppointmentController::class, 'create'])->name('records.create');
Route::post('/records', [AppointmentController::class, 'store'])->name('records.store');
Route::get('/records/{record}/edit', [AppointmentController::class, 'edit'])->name('records.edit');
Route::put('/records/{record}', [AppointmentController::class, 'update'])->name('records.update');
Route::delete('/records/{record}', [AppointmentController::class, 'destroy'])->name('records.destroy');
