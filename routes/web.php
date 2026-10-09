<?php

use App\Http\Controllers\Auth\PinLoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Loading\AdjustManifestEquipmentController;
use App\Http\Controllers\Loading\FinishManifestLoadingController;
use App\Http\Controllers\Loading\ListAvailableManifestsController;
use App\Http\Controllers\Loading\ListManifestConsignmentsController;
use App\Http\Controllers\Loading\LoadHandlingUnitController;
use App\Http\Controllers\Loading\LoadingController;
use App\Http\Controllers\Loading\ManifestSummaryController;
use App\Http\Controllers\Loading\ShowDepotManifestsController;
use App\Http\Controllers\Loading\ShowManifestConsignmentController;
use App\Http\Controllers\Loading\ShowManifestController;
use App\Http\Controllers\Loading\SimulateHandlingUnitScanController;
use App\Http\Controllers\Loading\UpdateConsignmentHoldBackController;
use App\Http\Controllers\Loading\UpdateManifestItemPositionController;
use App\Http\Controllers\Loading\UpdateManifestTrailerController;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', HomeController::class)->name('home');

// Touched before a silent re-login so a fresh session and CSRF cookie exist.
Route::get('session/ping', fn () => response()->noContent())->name('session.ping');

Route::middleware('guest')->group(function () {
    Route::post('login/pin', PinLoginController::class)->name('login.pin');

    Route::get('login/password', fn (Request $request) => Inertia::render('auth/password-login', [
        'canResetPassword' => Features::enabled(Features::resetPasswords()),
        'status' => $request->session()->get('status'),
    ]))->name('login.password');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => Inertia::render('dashboard'))
        ->name('dashboard');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('loading', LoadingController::class)
        ->name('loading.index');

    Route::get('loading/depots/{depot}', ShowDepotManifestsController::class)
        ->name('loading.depot');

    Route::get('loading/manifests/{manifest}', ShowManifestController::class)
        ->name('loading.manifest');

    Route::post('loading/manifests/{manifest}/equipment/adjust', AdjustManifestEquipmentController::class)
        ->name('loading.manifest.equipment.adjust');

    Route::post('loading/manifests/{manifest}/finish', [FinishManifestLoadingController::class, 'store'])
        ->name('loading.manifest.finish');

    Route::delete('loading/manifests/{manifest}/finish', [FinishManifestLoadingController::class, 'destroy'])
        ->name('loading.manifest.reopen');

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

    Route::patch('loading/manifests/{manifest}/consignments/{consignment}/hold-back', UpdateConsignmentHoldBackController::class)
        ->name('loading.manifest.consignment.hold-back');

    Route::post('loading/manifests/{manifest}/scan', LoadHandlingUnitController::class)
        ->name('loading.scan');

    Route::get('loading/manifests/{manifest}/simulate-scan', SimulateHandlingUnitScanController::class)
        ->name('loading.simulate-scan');

    Route::get('loading/manifests', ListAvailableManifestsController::class)
        ->name('loading.manifests');
});

require __DIR__.'/settings.php';
