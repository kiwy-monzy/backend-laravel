<?php

use Illuminate\Support\Facades\Route;
use Modules\Tickets\Http\Controllers\TicketsController;
use Modules\Tickets\Http\Controllers\TicketController;

/*
| Mounted at /admin/m/tickets behind session auth and `module:tickets`, which
| also refuses every write once the organization's plan has lapsed.
*/

Route::get('/', [TicketsController::class, 'index'])->name('index');

Route::get('/records', [TicketController::class, 'index'])->name('records.index');
Route::get('/records/data', [TicketController::class, 'data'])->name('records.data');
Route::get('/records/create', [TicketController::class, 'create'])->name('records.create');
Route::post('/records', [TicketController::class, 'store'])->name('records.store');
Route::get('/records/{record}/edit', [TicketController::class, 'edit'])->name('records.edit');
Route::put('/records/{record}', [TicketController::class, 'update'])->name('records.update');
Route::delete('/records/{record}', [TicketController::class, 'destroy'])->name('records.destroy');
