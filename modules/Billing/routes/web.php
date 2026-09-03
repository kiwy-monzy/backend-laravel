<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\BillingController;
use Modules\Billing\Http\Controllers\SubscriptionController;

/*
| Mounted at /admin/m/billing behind session auth and `module:billing`, which
| also refuses every write once the organization's plan has lapsed.
*/

Route::get('/', [BillingController::class, 'index'])->name('index');

Route::get('/records', [SubscriptionController::class, 'index'])->name('records.index');
Route::get('/records/data', [SubscriptionController::class, 'data'])->name('records.data');
Route::get('/records/create', [SubscriptionController::class, 'create'])->name('records.create');
Route::post('/records', [SubscriptionController::class, 'store'])->name('records.store');
Route::get('/records/{record}/edit', [SubscriptionController::class, 'edit'])->name('records.edit');
Route::put('/records/{record}', [SubscriptionController::class, 'update'])->name('records.update');
Route::delete('/records/{record}', [SubscriptionController::class, 'destroy'])->name('records.destroy');
