<?php

use Illuminate\Support\Facades\Route;
use Modules\ServiceHub\Http\Controllers\BookingController;
use Modules\ServiceHub\Http\Controllers\ProviderController;
use Modules\ServiceHub\Http\Controllers\RequestController;
use Modules\ServiceHub\Http\Controllers\ServiceController;
use Modules\ServiceHub\Http\Controllers\ServiceHubController;

/*
| Mounted at /admin/m/servicehub behind session auth and `module:servicehub`,
| which also refuses every write once the organization's plan has lapsed.
|
| Each of the four resources gets its own name prefix, because the module's
| tabs are matched on those patterns and a shared prefix would put every list
| under one section grant.
*/

Route::get('/', [ServiceHubController::class, 'index'])->name('index');

$resource = function (string $name, string $controller) {
    Route::get("/{$name}", [$controller, 'index'])->name("{$name}.index");
    Route::get("/{$name}/data", [$controller, 'data'])->name("{$name}.data");
    Route::get("/{$name}/create", [$controller, 'create'])->name("{$name}.create");
    Route::post("/{$name}", [$controller, 'store'])->name("{$name}.store");
    Route::get("/{$name}/{record}/edit", [$controller, 'edit'])->name("{$name}.edit");
    Route::put("/{$name}/{record}", [$controller, 'update'])->name("{$name}.update");
    Route::delete("/{$name}/{record}", [$controller, 'destroy'])->name("{$name}.destroy");
};

$resource('providers', ProviderController::class);
$resource('services', ServiceController::class);
$resource('requests', RequestController::class);
$resource('bookings', BookingController::class);

// A request becomes a booking in one step, carrying its own details across.
Route::post('/requests/{record}/convert', [RequestController::class, 'convert'])->name('requests.convert');
