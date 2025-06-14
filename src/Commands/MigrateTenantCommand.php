<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Kjos\Orchestra\Contexts\TenantContext;
use Kjos\Orchestra\Contexts\TenantManager;
use Kjos\Orchestra\Facades\Oor;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'orchestra:migrate')]
class MigrateTenantCommand extends Command
{
    protected $name        = 'orchestra:migrate';
    protected $description = 'Migrate a tenant database';
    protected $signature   = 'orchestra:migrate {name : The name of the tenant} {--all : Migrate all tenants}';

    public function handle(TenantManager $tenants): int
    {
        $name = $this->argument('name');



        if (! \app()->environment('testing')) {
            if ($this->option('all')) {
                $tenantsArray = Oor::getTenants();
                foreach ($tenantsArray as $t) {
                    $tenants->runFor($t, function (TenantContext $tenant) {
                        \runInConsole(fn () => $this->info("Migration du tenant: {$tenant->name}"));
                        Artisan::call('migrate', ['--path' => 'database/migrations/tenants', '--force' => true]);
                        \runInConsole(fn () => $this->line(Artisan::output()));
                    });
                }
            } else {
                $tenants->runFor($name, function (TenantContext $tenant) {
                    \runInConsole(fn () => $this->info("Migration du tenant: {$tenant->name}"));
                    Artisan::call('migrate', ['--path' => 'database/migrations/tenants', '--force' => true]);
                    \runInConsole(fn () => $this->line(Artisan::output()));
                });
            }
        }

        return self::SUCCESS;
    }
}
