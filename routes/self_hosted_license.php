<?php

use App\Http\Controllers\SuperAdminAppUpdateController;
use App\Http\Controllers\SuperAdminLicenseController;
use App\Http\Middleware\SuperAdminMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', SuperAdminMiddleware::class])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function (): void {
        Route::get('/settings/license', [SuperAdminLicenseController::class, 'index'])->name('settings.license');
        Route::post('/settings/license', [SuperAdminLicenseController::class, 'update'])->name('settings.license.update');
        Route::get('/settings/license/activation-request', [SuperAdminLicenseController::class, 'activationRequest'])->name('settings.license.activation-request');
        Route::delete('/settings/license', [SuperAdminLicenseController::class, 'unregister'])->name('settings.license.unregister');
        Route::post('/settings/license/upgrade-request', [SuperAdminLicenseController::class, 'upgradeRequest'])->name('settings.license.upgrade-request');
        Route::get('/settings/app-update', [SuperAdminAppUpdateController::class, 'index'])->name('settings.app-update');
        Route::post('/settings/app-update/check', [SuperAdminAppUpdateController::class, 'check'])->name('settings.app-update.check');
        Route::post('/settings/app-update/preflight', [SuperAdminAppUpdateController::class, 'preflight'])->name('settings.app-update.preflight');
        Route::post('/settings/app-update/heartbeat', [SuperAdminAppUpdateController::class, 'heartbeat'])->name('settings.app-update.heartbeat');
    });
