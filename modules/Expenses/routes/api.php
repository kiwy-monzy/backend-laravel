<?php

use Illuminate\Support\Facades\Route;
use Modules\Expenses\Http\Controllers\ExpensesApiController;

/*
| Mounted at /api/expenses behind bearer-token auth, named `api.expenses.*`.
*/

Route::get('/records', [ExpensesApiController::class, 'index'])->name('records.index');
Route::get('/records/{record}', [ExpensesApiController::class, 'show'])->name('records.show');
