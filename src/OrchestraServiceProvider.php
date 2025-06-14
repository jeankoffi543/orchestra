<?php

namespace Kjos\Orchestra;

use Illuminate\Support\ServiceProvider;
use Kjos\Orchestra\Contexts\TenantManager;

class OrchestraServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Load routes, views, migrations, etc.
    }

    public function register(): void
    {
        $this->commands([
            \Kjos\Orchestra\Commands\CreateTenantCommand::class,
            \Kjos\Orchestra\Commands\MigrateTenantCommand::class,
            \Kjos\Orchestra\Commands\DeleteTenantCommand::class,
            \Kjos\Orchestra\Commands\UpdateTenantCommand::class,
            \Kjos\Orchestra\Commands\LinkTenantCommand::class,
            \Kjos\Orchestra\Commands\UnlinkTenantCommand::class,
            \Kjos\Orchestra\Commands\ReloadDomainCommand::class,
            \Kjos\Orchestra\Commands\InstallTenantCommand::class,
            \Kjos\Orchestra\Commands\AddAutoloadNamespaceCommand::class,
            \Kjos\Orchestra\Commands\RemoveAutoloadNamespaceCommand::class,
            \Kjos\Orchestra\Commands\UninstallTenantCommand::class,
            \Kjos\Orchestra\Commands\RestoreTenantCommand::class,
        ]);
        //Register facade
        $this->app->singleton('oor', function ($app) {
            return new \Kjos\Orchestra\Facades\Orchestra($app);
        });

        $this->app->singleton(TenantManager::class, function () {
            return new TenantManager();
        });
    }
}
