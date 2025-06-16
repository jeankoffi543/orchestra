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
    protected $signature   = 'orchestra:migrate {name : The name of the tenant} {--all : Migrate all tenants} {--rollback-on-fail=true : Rollback on fail}';

    /**
     * Execute the console command.
     *
     * @param  TenantManager  $tenants
     * @return int
     */
    public function handle(TenantManager $tenants): int
    {
        /**
         * @var array<string>
         * */
        $migratedTenants = [];
        $rollbackOnFail  = $this->option('rollback-on-fail');

        try {
            if ($this->option('all')) {
                foreach (Oor::getTenants() as $t) {
                    $tenants->runFor($t, function (TenantContext $tenant) use (&$migratedTenants) {
                        runInConsole(fn () => $this->info("Migration du tenant: {$tenant->name}"));
                        Artisan::call('migrate', ['--path' => 'database/migrations/tenants', '--force' => true]);
                        runInConsole(fn () => $this->line(Artisan::output()));

                        // Ajouter à la liste si migration OK
                        $migratedTenants[] = $tenant->name;
                    });
                }
            } else {
                $name = $this->argument('name');
                $tenants->runFor($name, function (TenantContext $tenant) use (&$migratedTenants) {
                    runInConsole(fn () => $this->info("Migration du tenant: {$tenant->name}"));
                    Artisan::call('migrate', ['--path' => 'database/migrations/tenants', '--force' => true]);
                    runInConsole(fn () => $this->line(Artisan::output()));

                    $migratedTenants[] = $tenant->name;
                });
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            if ($rollbackOnFail && (\count($migratedTenants) > 0)) {
                $this->warn('Migration failed. Rolling back...');
                foreach ($migratedTenants as $tenantName) {
                    $tenants->runFor($tenantName, function (TenantContext $tenant) {
                        runInConsole(fn () => $this->info("Rollback du tenant: {$tenant->name}"));
                        Artisan::call('migrate:rollback', ['--path' => 'database/migrations/tenants', '--force' => true]);
                        runInConsole(fn () => $this->line(Artisan::output()));
                    });
                }
            }

            runInConsole(fn () => $this->error($e->getMessage()));

            return self::FAILURE;
        }
    }
}
