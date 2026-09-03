<?php

use Illuminate\Support\Facades\Route;
use Modules\Departments\Http\Controllers\DepartmentsController;
use Modules\Departments\Http\Controllers\DepartmentController;

/*
| Mounted at /admin/m/departments behind session auth and `module:departments`, which
| also refuses every write once the organization's plan has lapsed.
*/

Route::get('/', [DepartmentsController::class, 'index'])->name('index');

Route::get('/records', [DepartmentController::class, 'index'])->name('records.index');
Route::get('/records/data', [DepartmentController::class, 'data'])->name('records.data');
Route::get('/records/create', [DepartmentController::class, 'create'])->name('records.create');
Route::post('/records', [DepartmentController::class, 'store'])->name('records.store');
Route::get('/records/{record}/edit', [DepartmentController::class, 'edit'])->name('records.edit');
Route::put('/records/{record}', [DepartmentController::class, 'update'])->name('records.update');
Route::delete('/records/{record}', [DepartmentController::class, 'destroy'])->name('records.destroy');
