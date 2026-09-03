<?php

use Illuminate\Support\Facades\Route;
use Modules\Explorer\Http\Controllers\ExplorerController;

/*
| The map explorer, at /admin/m/explorer. A slippy map, the network behind it
| and the terrain model; there is nothing to persist, so these are all reads.
*/

Route::get('/', [ExplorerController::class, 'index'])->name('index');
Route::get('/network.json', [ExplorerController::class, 'network'])->name('network');
Route::get('/terrain', [ExplorerController::class, 'terrain'])->name('terrain');
