<?php

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
    });
