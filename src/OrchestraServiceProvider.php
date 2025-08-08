<?php

namespace Kjos\Orchestra;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\QueueManager;
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
            \Kjos\Orchestra\Commands\FreshCommand::class,
            \Kjos\Orchestra\Commands\OrchestraWorkCommand::class,
        ]);

        $this->app->extend(\Illuminate\Database\Console\Seeds\SeedCommand::class, function () {
            return new \Kjos\Orchestra\Commands\SeedCommand($this->app['db']);
        });

        $this->app->extend(\Illuminate\Database\Console\Migrations\MigrateCommand::class, function () {
            return new \Kjos\Orchestra\Commands\MigrateCommand($this->app['migrator'], $this->app['events']);
        });

        $this->app->extend(\Illuminate\Database\Console\Migrations\FreshCommand::class, function () {
            return new \Kjos\Orchestra\Commands\FreshCommand();
        });

        $this->app->extend(\Illuminate\Queue\Console\WorkCommand::class, function () {
            $manager              = $this->app->make(QueueManager::class);
            $events               = $this->app->make(Dispatcher::class);
            $cache                = $this->app->make(Repository::class);
            $exceptionHandler     = $this->app->make(ExceptionHandler::class);
            $isDownForMaintenance = fn () => $this->app->isDownForMaintenance();

            $worker = new \Illuminate\Queue\Worker($manager, $events, $exceptionHandler, $isDownForMaintenance);

            return new \Kjos\Orchestra\Commands\WorkCommand($worker, $cache);
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
