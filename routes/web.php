<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JwtController;

Route::get('/', [JwtController::class, 'showConsole'])->name('jwt.console');
Route::post('/jwt/send', [JwtController::class, 'send'])->name('jwt.send');
Route::post('/jwt/generate', [JwtController::class, 'generate'])->name('jwt.generate');
