<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Kjos\Orchestra\Facades\Oor;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'orchestra:delete')]
class DeleteTenantCommand extends Command
{
    /**
     * Console command name
     *
     * @var string
     */
    protected $name = 'orchestra:delete';

    /**
     * Console command description
     *
     * @var string
     */
    protected $description = 'Remove a tenant.';

    /**
     * Console command signature
     *
     * @var string
     */
    protected $signature = 'orchestra:delete {name} {--driver= : Specify a database driver}';

    public function handle(): int
    {
        try {
            // Récupérer les arguments et options correctement
            $name   = $this->argument('name');
            $driver = $this->option('driver');
            $driver = $driver ?? \getDriver(\base_path('.env'));

            // Vérification de la validité des données
            if (empty($name)) {
                \runInConsole(fn () => $this->error('The tenant name is required.'));

                return Command::FAILURE;
            }

            // Affichage pour vérifier les valeurs
            \runInConsole(fn () => $this->info("Deleting tenant: $name"));

            Oor::delete($name, $driver);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            \runInConsole(fn () => $this->error($e->getMessage()));

            return Command::FAILURE;
        }
    }
}
