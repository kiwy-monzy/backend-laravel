<?php

use Illuminate\Support\Facades\Route;
use Modules\Workerly\Http\Controllers\WorkerlyApiController;

/*
| Mounted at /api/workerly behind bearer-token auth, named `api.workerly.*`.
*/

Route::get('/records', [WorkerlyApiController::class, 'index'])->name('records.index');
Route::get('/records/{record}', [WorkerlyApiController::class, 'show'])->name('records.show');
