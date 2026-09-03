<?php

use Illuminate\Support\Facades\Route;
use Modules\Expenses\Http\Controllers\ExpensesController;
use Modules\Expenses\Http\Controllers\ExpenseController;

/*
| Mounted at /admin/m/expenses behind session auth and `module:expenses`, which
| also refuses every write once the organization's plan has lapsed.
*/

Route::get('/', [ExpensesController::class, 'index'])->name('index');

Route::get('/records', [ExpenseController::class, 'index'])->name('records.index');
Route::get('/records/data', [ExpenseController::class, 'data'])->name('records.data');
Route::get('/records/create', [ExpenseController::class, 'create'])->name('records.create');
Route::post('/records', [ExpenseController::class, 'store'])->name('records.store');
Route::get('/records/{record}/edit', [ExpenseController::class, 'edit'])->name('records.edit');
Route::put('/records/{record}', [ExpenseController::class, 'update'])->name('records.update');
Route::delete('/records/{record}', [ExpenseController::class, 'destroy'])->name('records.destroy');
