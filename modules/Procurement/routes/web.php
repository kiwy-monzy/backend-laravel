<?php

use Illuminate\Support\Facades\Route;
use Modules\Procurement\Http\Controllers\ProcurementController;
use Modules\Procurement\Http\Controllers\ProcurementDocumentController;
use Modules\Procurement\Http\Controllers\PurchaseRequestController;

/*
| Mounted at /admin/m/procurement behind session auth and `module:procurement`, which
| also refuses every write once the organization's plan has lapsed.
*/

Route::get('/', [ProcurementController::class, 'index'])->name('index');

Route::get('/records', [PurchaseRequestController::class, 'index'])->name('records.index');
Route::get('/records/data', [PurchaseRequestController::class, 'data'])->name('records.data');
Route::get('/records/create', [PurchaseRequestController::class, 'create'])->name('records.create');
Route::post('/records', [PurchaseRequestController::class, 'store'])->name('records.store');
Route::get('/records/{record}/edit', [PurchaseRequestController::class, 'edit'])->name('records.edit');
Route::put('/records/{record}', [PurchaseRequestController::class, 'update'])->name('records.update');
Route::delete('/records/{record}', [PurchaseRequestController::class, 'destroy'])->name('records.destroy');

// Purchase orders and supplier bills — the inbound half of the document book,
// listed here so the rail stays on Procurement while you work through them.
Route::get('/documents', [ProcurementDocumentController::class, 'index'])->name('documents.index');
