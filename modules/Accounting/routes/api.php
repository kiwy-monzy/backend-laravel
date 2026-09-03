<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\AccountingApiController;

/*
| Mounted at /api/accounting behind bearer-token auth, named `api.accounting.*`.
*/

Route::get('/records', [AccountingApiController::class, 'index'])->name('records.index');
Route::get('/records/{record}', [AccountingApiController::class, 'show'])->name('records.show');
