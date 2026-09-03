<?php

use Illuminate\Support\Facades\Route;
use Modules\Invoicing\Http\Controllers\DocumentController;
use Modules\Invoicing\Http\Controllers\InvoicingController;
use Modules\Invoicing\Http\Controllers\ItemController;
use Modules\Invoicing\Http\Controllers\PaymentController;
use Modules\Invoicing\Http\Controllers\RecurringController;

/*
| Mounted at /admin/m/invoicing behind session auth and `module:invoicing`,
| which also refuses every write once the organization's plan has lapsed.
*/

Route::get('/', [InvoicingController::class, 'index'])->name('index');

Route::get('/items', [ItemController::class, 'index'])->name('items.index');
Route::get('/items/create', [ItemController::class, 'create'])->name('items.create');
Route::post('/items', [ItemController::class, 'store'])->name('items.store');
Route::get('/items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
Route::put('/items/{item}', [ItemController::class, 'update'])->name('items.update');
Route::delete('/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');

Route::get('/invoices', [DocumentController::class, 'index'])->name('invoices.index');
Route::get('/invoices/data', [DocumentController::class, 'data'])->name('invoices.data');
Route::get('/invoices/create', [DocumentController::class, 'create'])->name('invoices.create');
Route::post('/invoices', [DocumentController::class, 'store'])->name('invoices.store');
Route::get('/invoices/{document}/edit', [DocumentController::class, 'edit'])->name('invoices.edit');
Route::put('/invoices/{document}', [DocumentController::class, 'update'])->name('invoices.update');
Route::delete('/invoices/{document}', [DocumentController::class, 'destroy'])->name('invoices.destroy');
Route::post('/invoices/{document}/send', [DocumentController::class, 'send'])->name('invoices.send');
Route::post('/invoices/{document}/void', [DocumentController::class, 'void'])->name('invoices.void');
Route::post('/invoices/{document}/payments', [DocumentController::class, 'addPayment'])->name('invoices.payments');

// Money received, across every document — the question a document list cannot
// answer on its own.
Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');

// Standing instructions that raise an ordinary invoice on a cycle.
Route::get('/recurring', [RecurringController::class, 'index'])->name('recurring.index');
Route::get('/recurring/create', [RecurringController::class, 'create'])->name('recurring.create');
Route::post('/recurring', [RecurringController::class, 'store'])->name('recurring.store');
Route::get('/recurring/{record}/edit', [RecurringController::class, 'edit'])->name('recurring.edit');
Route::put('/recurring/{record}', [RecurringController::class, 'update'])->name('recurring.update');
Route::delete('/recurring/{record}', [RecurringController::class, 'destroy'])->name('recurring.destroy');
Route::post('/recurring/{record}/issue', [RecurringController::class, 'issue'])->name('recurring.issue');
