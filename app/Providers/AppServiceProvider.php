<?php

namespace App\Providers;

use App\Contracts\AccessControllerClient;
use App\Services\AccessControllerClientRouter;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AccessControllerClient::class, AccessControllerClientRouter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.metronic');
    }
}
