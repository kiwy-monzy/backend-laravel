<?php

use Illuminate\Support\Facades\Route;
use Modules\Reports\Http\Controllers\ReportsController;

/*
| Read-only views over the other modules, at /admin/m/reports.
|
| Reports owns no tables — every page here is a query across Invoicing,
| Expenses, Inventory and CRM, which is the only place profit can honestly be
| computed from.
*/

Route::get('/', [ReportsController::class, 'index'])->name('index');
Route::get('/financial', [ReportsController::class, 'financial'])->name('financial');
Route::get('/sales', [ReportsController::class, 'sales'])->name('sales');
Route::get('/customers', [ReportsController::class, 'customers'])->name('customers');
Route::get('/inventory', [ReportsController::class, 'inventory'])->name('inventory');
