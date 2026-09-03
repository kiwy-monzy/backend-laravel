<?php

use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\Controllers\CrmController;
use Modules\Crm\Http\Controllers\CustomerController;
use Modules\Crm\Http\Controllers\LeadController;

/*
| Mounted at /admin/m/crm by ModuleServiceProvider, behind session auth and
| `module:crm`. Route names are prefixed with `crm.` automatically, so
| `->name('customers.index')` here is `crm.customers.index` everywhere else.
*/

Route::get('/', [CrmController::class, 'index'])->name('index');

Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
Route::get('/customers/data', [CustomerController::class, 'data'])->name('customers.data');
Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
Route::get('/leads/create', [LeadController::class, 'create'])->name('leads.create');
Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
Route::get('/leads/{lead}/edit', [LeadController::class, 'edit'])->name('leads.edit');
Route::put('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
Route::post('/leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
Route::delete('/leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');
