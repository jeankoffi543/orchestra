<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Queue\Console\WorkCommand as BaseWorkCommand;
use Illuminate\Queue\Worker;
use Kjos\Orchestra\Contexts\TenantContext;
use Kjos\Orchestra\Contexts\TenantManager;
use Kjos\Orchestra\Facades\Oor;

class WorkCommand extends BaseWorkCommand
{
    protected $name = 'queue:work';

    public function __construct(Worker $worker, Cache $cache)
    {
        parent::__construct($worker, $cache);
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
                'Tenants database connections to work, use "all" for all tenants or separate by commas for multiple tenants'
            )
        );
    }

    public function handle(): int
    {
        $tenants = $this->option('tenants');
        if (! empty($tenants) && !\current($tenants)) {
            $response = $this->confirm('You are about to seed all tenants. Are you sure?', false);
            if (! $response) {
                $this->warn('Seed cancelled');

                return Command::FAILURE;
            }

            return $this->runForMany(Oor::getTenants());
        } elseif (! empty($tenants)) {
            return $this->runForMany($tenants);
        }

        // Comportement par défaut si --tenants n'est pas spécifié
        return parent::handle() ?? Command::SUCCESS;
    }

    /**
     * Runs the command for multiple tenants.
     *
     * @param array<string> $tenants An array of tenant names or 'all' to run for all tenants
     * @return int The command exit status
     */
    private function runForMany(array $tenants): int
    {
        $tenantManager = new TenantManager();
        $tenants       = \current($tenants) === 'all' ?
            Oor::getTenants() :
            $tenants;

        foreach ($tenants as $tenant_) {
            return  $tenantManager->runFor($tenant_, function (TenantContext $tenant) {
                $this->info("<bg=blue>Tenant:</> {$tenant->name}");

                return parent::handle() ?? Command::SUCCESS;
            });
        }

        return Command::FAILURE;
    }
}
