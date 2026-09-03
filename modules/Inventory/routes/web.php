<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\InventoryController;
use Modules\Inventory\Http\Controllers\StockController;

/*
| Mounted at /admin/m/inventory behind session auth and `module:inventory`, which
| also refuses every write once the organization's plan has lapsed.
*/

Route::get('/', [InventoryController::class, 'index'])->name('index');

Route::get('/records', [StockController::class, 'index'])->name('records.index');
Route::get('/records/data', [StockController::class, 'data'])->name('records.data');
Route::get('/records/create', [StockController::class, 'create'])->name('records.create');
Route::post('/records', [StockController::class, 'store'])->name('records.store');
Route::get('/records/{record}/edit', [StockController::class, 'edit'])->name('records.edit');
Route::put('/records/{record}', [StockController::class, 'update'])->name('records.update');
Route::delete('/records/{record}', [StockController::class, 'destroy'])->name('records.destroy');
