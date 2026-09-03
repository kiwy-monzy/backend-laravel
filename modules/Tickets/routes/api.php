<?php

use Illuminate\Support\Facades\Route;
use Modules\Tickets\Http\Controllers\TicketsApiController;
use Modules\Tickets\Http\Controllers\PublicVolunteerController;

/*
| Mounted at /api/tickets behind bearer-token auth, named `api.tickets.*`.
*/

Route::get('/records', [TicketsApiController::class, 'index'])->name('records.index');
Route::get('/records/{record}', [TicketsApiController::class, 'show'])->name('records.show');

// Public volunteer submission - no authentication required
Route::post('/volunteer', [PublicVolunteerController::class, 'store'])->name('volunteer.store')->withoutMiddleware(['auth.api']);
