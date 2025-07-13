<?php

namespace Kjos\Orchestra;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Kjos\Orchestra\Contexts\TenantManager;

class OrchestraServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Load routes, views, migrations, etc.
        $schedule = $this->app->make(Schedule::class);
        $schedule->command('orchestra:virtualhost:scanner')
            ->everySecond()
            ->withoutOverlapping(5);
        // $schedule->command('orchestra:reload-domain')->everyMinute();
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
            \Kjos\Orchestra\Commands\CreateDeployerCommand::class,
            \Kjos\Orchestra\Commands\MakeVirtualHostCommand::class,
            \Kjos\Orchestra\Commands\RemoveDeployerCommand::class,
            \Kjos\Orchestra\Commands\MakeVirtualHostScanCommand::class,
            \Kjos\Orchestra\Commands\RemoveVirtualHostsCommand::class,
            \Kjos\Orchestra\Commands\DatabaseCreateCommand::class,
        ]);

        $this->app->extend(\Illuminate\Database\Console\Seeds\SeedCommand::class, function () {
            return new \Kjos\Orchestra\Commands\SeedCommand($this->app['db']);
        });

        $this->app->extend(\Illuminate\Database\Console\Migrations\MigrateCommand::class, function () {
            return new \Kjos\Orchestra\Commands\MigrateCommand($this->app['migrator'], $this->app['events']);
        });

        //Register facade
        $this->app->singleton('oor', function ($app) {
            return new \Kjos\Orchestra\Facades\Orchestra($app);
        });

        $this->app->singleton(TenantManager::class, function () {
            return new TenantManager();
        });
    }
}
