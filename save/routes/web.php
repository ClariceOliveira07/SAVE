<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [\App\Http\Controllers\AuthController::class, 'createLogin'])->name('login');
    Route::post('/entrar', [\App\Http\Controllers\AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/cadastro', [\App\Http\Controllers\AuthController::class, 'createRegistration'])->name('register');
    Route::post('/cadastro', [\App\Http\Controllers\AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('/sair', [\App\Http\Controllers\AuthController::class, 'logout'])->name('logout');
    Route::get('/despensa', [\App\Http\Controllers\AlimentoScannerController::class, 'index'])->name('pantry.index');
    Route::get('/escanear-alimentos', [\App\Http\Controllers\AlimentoScannerController::class, 'index'])->name('foods.scan');
    Route::post('/escanear-alimentos/analisar', [\App\Http\Controllers\AlimentoScannerController::class, 'analyze'])
        ->middleware('throttle:10,1')->name('foods.analyze');
    Route::post('/alimentos', [\App\Http\Controllers\AlimentoScannerController::class, 'store'])->name('foods.store');
});
