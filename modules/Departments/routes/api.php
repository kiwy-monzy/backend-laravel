<?php

use Illuminate\Support\Facades\Route;
use Modules\Departments\Http\Controllers\DepartmentsApiController;

/*
| Mounted at /api/departments behind bearer-token auth, named `api.departments.*`.
*/

Route::get('/records', [DepartmentsApiController::class, 'index'])->name('records.index');
Route::get('/records/{record}', [DepartmentsApiController::class, 'show'])->name('records.show');
