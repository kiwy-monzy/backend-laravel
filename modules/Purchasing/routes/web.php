<?php

use Illuminate\Support\Facades\Route;
use Modules\Purchasing\Http\Controllers\PurchasingController;
use Modules\Purchasing\Http\Controllers\PurchaseOrderController;

/*
| Mounted at /admin/m/purchasing behind session auth and `module:purchasing`, which
| also refuses every write once the organization's plan has lapsed.
*/

Route::get('/', [PurchasingController::class, 'index'])->name('index');

Route::get('/records', [PurchaseOrderController::class, 'index'])->name('records.index');
Route::get('/records/data', [PurchaseOrderController::class, 'data'])->name('records.data');
Route::get('/records/create', [PurchaseOrderController::class, 'create'])->name('records.create');
Route::post('/records', [PurchaseOrderController::class, 'store'])->name('records.store');
Route::get('/records/{record}/edit', [PurchaseOrderController::class, 'edit'])->name('records.edit');
Route::put('/records/{record}', [PurchaseOrderController::class, 'update'])->name('records.update');
Route::delete('/records/{record}', [PurchaseOrderController::class, 'destroy'])->name('records.destroy');
