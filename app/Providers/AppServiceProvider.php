<?php

namespace App\Providers;

use App\Http\Middleware\RoleMiddleware;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::aliasMiddleware('role', RoleMiddleware::class);

        view()->composer('layouts.admin', function ($view): void {
            $authUser = auth()->user();

            if (! $authUser) {
                $view->with([
                    'sidebarOwners' => collect(),
                ]);

                return;
            }

            $owners = $authUser->isSuperAdmin()
                ? User::query()->tenants()->orderBy('name')->get()
                : collect();

            $view->with([
                'sidebarOwners' => $owners,
            ]);
        });
    }
}
