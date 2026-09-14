<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Loading\ListAvailableManifestsController;
use App\Http\Controllers\Loading\LoadHandlingUnitController;
use App\Http\Controllers\Loading\LoadingController;
use App\Http\Controllers\Loading\SimulateHandlingUnitScanController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => Inertia::render('dashboard'))
        ->name('dashboard');

    Route::get('loading', LoadingController::class)
        ->name('loading.index');

    Route::post('loading/manifests/{manifest}/scan', LoadHandlingUnitController::class)
        ->name('loading.scan');

    Route::get('loading/manifests/{manifest}/simulate-scan', SimulateHandlingUnitScanController::class)
        ->name('loading.simulate-scan');

    Route::get('loading/manifests', ListAvailableManifestsController::class)
        ->name('loading.manifests');
});

require __DIR__.'/settings.php';
