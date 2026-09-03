<?php

use Illuminate\Support\Facades\Route;
use Modules\Cart\Http\Controllers\CartController;
use Modules\Cart\Http\Controllers\OrderController;

/*
| Mounted at /admin/m/cart behind session auth and `module:cart`, which
| also refuses every write once the organization's plan has lapsed.
*/

Route::get('/', [CartController::class, 'index'])->name('index');

Route::get('/records', [OrderController::class, 'index'])->name('records.index');
Route::get('/records/data', [OrderController::class, 'data'])->name('records.data');
Route::get('/records/create', [OrderController::class, 'create'])->name('records.create');
Route::post('/records', [OrderController::class, 'store'])->name('records.store');
Route::get('/records/{record}/edit', [OrderController::class, 'edit'])->name('records.edit');
Route::put('/records/{record}', [OrderController::class, 'update'])->name('records.update');
Route::delete('/records/{record}', [OrderController::class, 'destroy'])->name('records.destroy');
