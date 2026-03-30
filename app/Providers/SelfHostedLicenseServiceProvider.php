<?php

namespace App\Providers;

use App\Services\SelfHostedLicenseViewDataService;
use Illuminate\Support\ServiceProvider;

class SelfHostedLicenseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        view()->composer('layouts.admin', function ($view): void {
            $view->with(app(SelfHostedLicenseViewDataService::class)->forAdminLayout());
        });
    }
}
