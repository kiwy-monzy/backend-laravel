<?php

use Illuminate\Support\Facades\Route;
use Modules\Fulfillment\Http\Controllers\FulfillmentApiController;

/*
| Mounted at /api/fulfillment behind bearer-token auth, named `api.fulfillment.*`.
*/

Route::get('/records', [FulfillmentApiController::class, 'index'])->name('records.index');
Route::get('/records/{record}', [FulfillmentApiController::class, 'show'])->name('records.show');
