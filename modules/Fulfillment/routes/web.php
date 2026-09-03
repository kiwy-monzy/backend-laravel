<?php

use Illuminate\Support\Facades\Route;
use Modules\Fulfillment\Http\Controllers\FulfillmentController;
use Modules\Fulfillment\Http\Controllers\ShipmentController;

/*
| Mounted at /admin/m/fulfillment behind session auth and `module:fulfillment`, which
| also refuses every write once the organization's plan has lapsed.
*/

Route::get('/', [FulfillmentController::class, 'index'])->name('index');

Route::get('/records', [ShipmentController::class, 'index'])->name('records.index');
Route::get('/records/data', [ShipmentController::class, 'data'])->name('records.data');
Route::get('/records/create', [ShipmentController::class, 'create'])->name('records.create');
Route::post('/records', [ShipmentController::class, 'store'])->name('records.store');
Route::get('/records/{record}/edit', [ShipmentController::class, 'edit'])->name('records.edit');
Route::put('/records/{record}', [ShipmentController::class, 'update'])->name('records.update');
Route::delete('/records/{record}', [ShipmentController::class, 'destroy'])->name('records.destroy');
