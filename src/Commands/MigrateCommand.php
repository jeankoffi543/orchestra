<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Console\Migrations\MigrateCommand as BaseMigrateCommand;
use Illuminate\Database\Migrations\Migrator;
use Kjos\Orchestra\Contexts\TenantContext;
use Kjos\Orchestra\Contexts\TenantManager;
use Kjos\Orchestra\Facades\Oor;

class MigrateCommand extends BaseMigrateCommand
{
    public function __construct(Migrator $migrator, Dispatcher $dispatcher)
    {
        parent::__construct($migrator, $dispatcher);
    }

    protected function configure(): void
    {
        parent::configure();

        // Ajout de l'option --tenants
        $this->getDefinition()->addOption(
            new \Symfony\Component\Console\Input\InputOption(
                'tenants',
                null,
                \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL | \Symfony\Component\Console\Input\InputOption::VALUE_IS_ARRAY,
                'Tenant database connections to seed'
            )
        );
    }

    public function handle(): int
    {
        $tenantManager = new TenantManager();
        $tenants       = $this->option('tenants');
        if (! empty($tenants) && \current($tenants)) {
            return  $tenantManager->runFor(\current($tenants), function (TenantContext $tenant) {
                $this->info("<bg=blue>Tenant:</> {$tenant->name}");

                return parent::handle();
            });
        } elseif (! empty($tenants) && !\current($tenants)) {
            $response = $this->confirm('You are about to migrate all tenants. Are you sure?', false);
            if (! $response) {
                $this->warn('Migration cancelled');

                return Command::FAILURE;
            }

            foreach (Oor::getTenants() as $t) {
                return  $tenantManager->runFor($t, function (TenantContext $tenant) {
                    $this->info("<bg=blue>Tenant:</> {$tenant->name}");

                    return parent::handle();
                });
            }
        }

        // Comportement par défaut si --tenants n'est pas spécifié
        return parent::handle();
    }
}
