<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Kjos\Orchestra\Facades\Oor;

class TenantServiceProvider extends ServiceProvider
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
        if (app()->runningInConsole()) return;
        app(\Kjos\Orchestra\Facades\Oor::class)::initialize();

        $this->mapRoutes();
    }

    /**
     * Maps the routes for the tenant.
     *
     * Depending on whether the current tenant is the master or a slave,
     * it maps the routes for the master or the slave.
     *
     * @return void
     */
    public function mapRoutes(): void
    {
        if (Oor::getCurrent() === config('orchestra.master.name')) {
            // matser routes
            foreach (config('orchestra.master.route') as $route) {
                $route = collect($route);
                Route::prefix($route->get('prefix', 'api'))
                    ->middleware($route->get('middleware', 'api'))
                    ->name($route->get('name', 'master') . '.')
                    ->group(base_path("site/" . config('orchestra.master.name') . "/routes/" . $route->get('file_name', 'api.php')));
            }
        } else {
            // slave routes
            foreach (config('orchestra.slave.route') as $route) {
                $route = collect($route);
                Route::prefix($route->get('prefix', 'api'))
                    ->middleware($route->get('middleware', 'api'))
                    ->name($route->get('name', 'slave') . '.')
                    ->group(base_path("site/" . Oor::getCurrent() . "/routes/" . $route->get('file_name', 'api.php')));
            }
        }
    }
}
