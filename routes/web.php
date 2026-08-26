<?php

use App\Http\Controllers\ApiConsoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ApiConsoleController::class, 'showConsole'])->name('console.show');
Route::post('/console/send', [ApiConsoleController::class, 'send'])->name('console.send');
Route::post('/console/iaaas-keys', [ApiConsoleController::class, 'saveIaaasCredentials'])->name('console.iaaas-keys');
