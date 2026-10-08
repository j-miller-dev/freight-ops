<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Loading\ListAvailableManifestsController;
use App\Http\Controllers\Loading\ListManifestConsignmentsController;
use App\Http\Controllers\Loading\LoadHandlingUnitController;
use App\Http\Controllers\Loading\LoadingController;
use App\Http\Controllers\Loading\ManifestSummaryController;
use App\Http\Controllers\Loading\ShowDepotManifestsController;
use App\Http\Controllers\Loading\ShowManifestConsignmentController;
use App\Http\Controllers\Loading\ShowManifestController;
use App\Http\Controllers\Loading\SimulateHandlingUnitScanController;
use App\Http\Controllers\Loading\UpdateManifestItemPositionController;
use App\Http\Controllers\Loading\UpdateManifestTrailerController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => Inertia::render('dashboard'))
        ->name('dashboard');

    Route::get('loading', LoadingController::class)
        ->name('loading.index');

    Route::get('loading/depots/{depot}', ShowDepotManifestsController::class)
        ->name('loading.depot');

    Route::get('loading/manifests/{manifest}', ShowManifestController::class)
        ->name('loading.manifest');

    Route::get('loading/manifests/{manifest}/summary', ManifestSummaryController::class)
        ->name('loading.manifest.summary');

    Route::patch('loading/manifests/{manifest}/trailer', UpdateManifestTrailerController::class)
        ->name('loading.manifest.trailer');

    Route::patch('loading/manifests/{manifest}/pallets/{handlingUnit}/position', UpdateManifestItemPositionController::class)
        ->name('loading.manifest.position');

    Route::get('loading/manifests/{manifest}/consignments', ListManifestConsignmentsController::class)
        ->name('loading.manifest.consignments');

    Route::get('loading/manifests/{manifest}/consignments/{consignment}', ShowManifestConsignmentController::class)
        ->name('loading.manifest.consignment');

    Route::post('loading/manifests/{manifest}/scan', LoadHandlingUnitController::class)
        ->name('loading.scan');

    Route::get('loading/manifests/{manifest}/simulate-scan', SimulateHandlingUnitScanController::class)
        ->name('loading.simulate-scan');

    Route::get('loading/manifests', ListAvailableManifestsController::class)
        ->name('loading.manifests');
});

require __DIR__.'/settings.php';
