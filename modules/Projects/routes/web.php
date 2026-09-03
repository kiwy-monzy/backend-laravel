<?php

use Illuminate\Support\Facades\Route;
use Modules\Projects\Http\Controllers\ProjectsController;
use Modules\Projects\Http\Controllers\ProjectController;

/*
| Mounted at /admin/m/projects behind session auth and `module:projects`, which
| also refuses every write once the organization's plan has lapsed.
*/

Route::get('/', [ProjectsController::class, 'index'])->name('index');

Route::get('/records', [ProjectController::class, 'index'])->name('records.index');
Route::get('/records/data', [ProjectController::class, 'data'])->name('records.data');
Route::get('/records/create', [ProjectController::class, 'create'])->name('records.create');
Route::post('/records', [ProjectController::class, 'store'])->name('records.store');
Route::get('/records/{record}/edit', [ProjectController::class, 'edit'])->name('records.edit');
Route::put('/records/{record}', [ProjectController::class, 'update'])->name('records.update');
Route::delete('/records/{record}', [ProjectController::class, 'destroy'])->name('records.destroy');
