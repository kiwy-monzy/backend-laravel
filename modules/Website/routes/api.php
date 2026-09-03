<?php

use Modules\Website\Http\Controllers\WebsiteController;
use Illuminate\Support\Facades\Route;

/*
| Mounted at /api/website behind bearer-token auth. Names are prefixed
| `api.website.` so they cannot collide with the web routes above.
*/

Route::get('/', [WebsiteController::class, 'apiIndex'])->name('index');
