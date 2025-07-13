<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionResolverInterface as Resolver;
use Illuminate\Database\Console\Seeds\SeedCommand as BaseSeedCommand;
use Kjos\Orchestra\Contexts\TenantContext;
use Kjos\Orchestra\Contexts\TenantManager;
use Kjos\Orchestra\Facades\Oor;

class SeedCommand extends BaseSeedCommand
{
    protected $name        = 'db:seed';
    protected $description = 'Seed the database with records with tenant support';

    public function __construct(Resolver $resolver)
    {
        parent::__construct($resolver);
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
            $response = $this->confirm('You are about to seed all tenants. Are you sure?', false);
            if (! $response) {
                $this->warn('Seed cancelled');

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
