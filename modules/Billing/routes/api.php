<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\BillingApiController;

/*
| Mounted at /api/billing behind bearer-token auth, named `api.billing.*`.
*/

Route::get('/records', [BillingApiController::class, 'index'])->name('records.index');
Route::get('/records/{record}', [BillingApiController::class, 'show'])->name('records.show');
