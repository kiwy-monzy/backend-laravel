<?php

use Illuminate\Support\Facades\Route;
use Modules\Procurement\Http\Controllers\ProcurementApiController;

/*
| Mounted at /api/procurement behind bearer-token auth, named `api.procurement.*`.
*/

Route::get('/records', [ProcurementApiController::class, 'index'])->name('records.index');
Route::get('/records/{record}', [ProcurementApiController::class, 'show'])->name('records.show');
