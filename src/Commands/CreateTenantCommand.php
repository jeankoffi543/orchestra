<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Kjos\Orchestra\Facades\Oor;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'orchestra:create')]
class CreateTenantCommand extends Command
{
    /**
     * Console command name
     *
     * @var string
     */
    protected $name = 'orchestra:create';

    /**
     * Console command description
     *
     * @var string
     */
    protected $description = 'Create a new tenant.';

    /**
     * Console command signature
     *
     * @var string
     */
    protected $signature = 'orchestra:create {name} {--force} {--domain= : Specify a domain name} {--driver= : Specify a database driver} {--migrate : Migrate the database.}';

    public function handle(): int
    {
        try {
            // Récupérer les arguments et options correctement
            $name    = $this->argument('name');
            $domain  = $this->option('domain');
            $driver  = $this->option('driver');
            $migrate = $this->option('migrate');
            $driver  = $driver ?? \getDriver(\base_path('.env'));

            // Vérification de la validité des données
            if (empty($name)) {
                \runInConsole(fn () => $this->error('The tenant name is required.'));

                return Command::FAILURE;
            }

            // Affichage pour vérifier les valeurs
            \runInConsole(fn () => $this->info("Creating tenant: $name"));

            if ($domain) {
                \runInConsole(fn () => $this->info("With domain: $domain"));
            }

            $migrate = $migrate ? true : false;

            Oor::create([
                'name'    => $name,
                'domains' => $domain,
            ], $driver, $migrate);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            \runInConsole(fn () => $this->error($e->getMessage()));

            return Command::FAILURE;
        }
    }
}
