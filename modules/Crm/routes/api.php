<?php

use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\Controllers\CrmApiController;

/*
| Mounted at /api/crm behind bearer-token auth. Names are prefixed
| `api.crm.` so they cannot collide with the web routes.
*/

Route::get('/customers', [CrmApiController::class, 'index'])->name('customers.index');
Route::get('/customers/{customer}', [CrmApiController::class, 'show'])->name('customers.show');
Route::post('/customers', [CrmApiController::class, 'store'])->name('customers.store');
