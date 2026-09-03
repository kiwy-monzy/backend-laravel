<?php

use Illuminate\Support\Facades\Route;
use Modules\Workerly\Http\Controllers\WorkerlyController;
use Modules\Workerly\Http\Controllers\ShiftController;

/*
| Mounted at /admin/m/workerly behind session auth and `module:workerly`, which
| also refuses every write once the organization's plan has lapsed.
*/

Route::get('/', [WorkerlyController::class, 'index'])->name('index');

Route::get('/records', [ShiftController::class, 'index'])->name('records.index');
Route::get('/records/data', [ShiftController::class, 'data'])->name('records.data');
Route::get('/records/create', [ShiftController::class, 'create'])->name('records.create');
Route::post('/records', [ShiftController::class, 'store'])->name('records.store');
Route::get('/records/{record}/edit', [ShiftController::class, 'edit'])->name('records.edit');
Route::put('/records/{record}', [ShiftController::class, 'update'])->name('records.update');
Route::delete('/records/{record}', [ShiftController::class, 'destroy'])->name('records.destroy');
