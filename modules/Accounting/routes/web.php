<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\AccountingController;
use Modules\Accounting\Http\Controllers\AccountController;
use Modules\Accounting\Http\Controllers\JournalController;

/*
| Mounted at /admin/m/accounting behind session auth and `module:accounting`, which
| also refuses every write once the organization's plan has lapsed.
*/

Route::get('/', [AccountingController::class, 'index'])->name('index');

Route::get('/records', [AccountController::class, 'index'])->name('records.index');
Route::get('/records/data', [AccountController::class, 'data'])->name('records.data');
Route::get('/records/create', [AccountController::class, 'create'])->name('records.create');
Route::post('/records', [AccountController::class, 'store'])->name('records.store');
Route::get('/records/{record}/edit', [AccountController::class, 'edit'])->name('records.edit');
Route::put('/records/{record}', [AccountController::class, 'update'])->name('records.update');
Route::delete('/records/{record}', [AccountController::class, 'destroy'])->name('records.destroy');

/*
| The journal is the only book written to. Ledger, trial balance, statements,
| fixed assets and the customer ledger are all folds over the same lines, so
| they are reads and cannot disagree with one another.
*/
Route::get('/journal', [JournalController::class, 'index'])->name('journal.index');
Route::get('/journal/data', [JournalController::class, 'journalData'])->name('journal.data');
Route::get('/journal/create', [JournalController::class, 'create'])->name('journal.create');
Route::post('/journal', [JournalController::class, 'store'])->name('journal.store');
Route::get('/journal/{entry}/edit', [JournalController::class, 'edit'])->name('journal.edit');
Route::put('/journal/{entry}', [JournalController::class, 'update'])->name('journal.update');
Route::delete('/journal/{entry}', [JournalController::class, 'destroy'])->name('journal.destroy');

Route::get('/ledger', [JournalController::class, 'ledger'])->name('journal.ledger');
Route::get('/trial-balance', [JournalController::class, 'trialBalance'])->name('journal.trial');
Route::get('/statements', [JournalController::class, 'statements'])->name('journal.statements');
Route::get('/fixed-assets', [JournalController::class, 'fixedAssets'])->name('journal.assets');
Route::get('/customer-ledger', [JournalController::class, 'customerLedger'])->name('journal.customers');
