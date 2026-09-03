<?php

use Illuminate\Support\Facades\Route;
use Modules\Projects\Http\Controllers\ProjectsApiController;

/*
| Mounted at /api/projects behind bearer-token auth, named `api.projects.*`.
*/

Route::get('/records', [ProjectsApiController::class, 'index'])->name('records.index');
Route::get('/records/{record}', [ProjectsApiController::class, 'show'])->name('records.show');
