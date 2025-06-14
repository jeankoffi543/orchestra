<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Kjos\Orchestra\Facades\Oor;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'orchestra:update')]
class UpdateTenantCommand extends Command
{
    /**
     * Console command name
     *
     * @var string
     */
    protected $name = 'orchestra:update';

    /**
     * Console command description
     *
     * @var string
     */
    protected $description = 'Update a existing tenant.';

    /**
     * Console command signature
     *
     * @var string
     */
    protected $signature = 'orchestra:update {name} {--by= : Specify the new tenant name} {--domain= : Specify the new tenant domain} {--driver= : Specify a database driver}';

    public function handle(): int
    {
        try {
            // Récupérer les arguments et options correctement
            $name   = $this->argument('name');
            $driver = $this->option('driver');
            $by     = $this->option('by');
            $domain = $this->option('domain');
            $driver = $driver ?? \getDriver(\base_path('.env'));

            // Vérification de la validité des données
            if (empty($name)) {
                \runInConsole(fn () => $this->error('The tenant name is required.'));

                return Command::FAILURE;
            }

            if (empty($by)) {
                \runInConsole(fn () => $this->error('The new tenant name is required.'));

                return Command::FAILURE;
            }

            // Affichage pour vérifier les valeurs
            \runInConsole(fn () => $this->info("Updating tenant: $name"));

            Oor::update(
                [
                    'name'   => $name,
                    'by'     => $by,
                    'domain' => $domain,
                ],
                $driver
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            \runInConsole(fn () => $this->error($e->getMessage()));

            return Command::FAILURE;
        }
    }
}
