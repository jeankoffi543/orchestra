<?php

namespace App\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Kjos\Orchestra\Facades\Concerns\InterractWithServiceProvider;
use Kjos\Orchestra\Facades\Oor;

class TenantServiceProvider extends ServiceProvider
{
    use InterractWithServiceProvider;

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
        $this->app->booted(function () {
            if (!\app()->runningInConsole() || $this->isArtisanCommand('route:list') || \app()->environment('testing')) {
                Oor::initialize();
                $this->mapRoutes();
            }
        });
    }

    /**
     * Maps the routes for the tenant.
     *
     * Depending on whether the current tenant is the master or a slave,
     * it maps the routes for the master or the slave.
     *
     * @return void
     */
    private function mapRoutes(): void
    {
        if (Oor::getCurrent() === \config('orchestra.master.name')) {
            // matser routes
            foreach (\config('orchestra.master.route') as $route) {
                $route = $this->routeToCollection($route);
                Route::prefix($route->get('prefix', 'api'))
                ->middleware($route->get('middleware', 'api'))
                    ->name($this->routeName($route))
                    ->group(\base_path('site/' . \config('orchestra.master.name') . '/routes/' . $route->get('file_name', 'api.php')));
            }
        } else {
            // slave routes
            foreach (\config('orchestra.slave.route') as $route) {
                $route = $this->routeToCollection($route);
                Route::prefix($route->get('prefix', 'api'))
                    ->middleware($route->get('middleware', 'api'))
                    ->name($this->routeName($route))
                    ->group($this->getSlavePath($route));
            }
        }
    }

    /**
     * Check if the current process is running an artisan command.
     *
     * @param  array<string>|string  $commands
     * @return bool
     */
    private function isArtisanCommand(array|string $commands): bool
    {
        $commands = (array) $commands;

        return \app()->runningInConsole()
            && isset($_SERVER['argv'][1])
            && \in_array($_SERVER['argv'][1], $commands, true);
    }

    /**
     * Returns the route name of the given route collection.
     *
     * @param  \Illuminate\Support\Collection<array-key, mixed>  $route
     * @return string|null
     */
    private function routeName(Collection $route): ?string
    {
        return $route->get('name') ? $route->get('name') . '.' : null;
    }
}
