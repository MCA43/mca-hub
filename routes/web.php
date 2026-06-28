<?php

use Illuminate\Support\Facades\Route;
use Mca\Hub\Http\Controllers\HubController;

$prefix = config('hub.routes.prefix', 'mca');
$middleware = config('hub.routes.middleware', ['web', 'auth', 'mca.hub.access']);
$namePrefix = config('hub.routes.name_prefix', 'mca.hub.');

Route::prefix($prefix)
    ->middleware($middleware)
    ->name($namePrefix)
    ->group(function () {
        Route::get('/', [HubController::class, 'index'])->name('index');
    });
