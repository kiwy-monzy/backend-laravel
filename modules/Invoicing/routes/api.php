<?php

use Illuminate\Support\Facades\Route;
use Modules\Invoicing\Http\Controllers\InvoicingApiController;

/*
| Mounted at /api/invoicing behind bearer-token auth, named `api.invoicing.*`.
*/

Route::get('/items', [InvoicingApiController::class, 'items'])->name('items');
Route::get('/invoices', [InvoicingApiController::class, 'documents'])->name('invoices');
Route::get('/invoices/{document}', [InvoicingApiController::class, 'show'])->name('invoices.show');
