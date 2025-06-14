<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Response;
use Kjos\Orchestra\Facades\Oor;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'orchestra:restore')]
class RestoreTenantCommand extends Command
{
    /**
     * Console command name
     *
     * @var string
     */
    protected $name = 'orchestra:restore';

    /**
     * Console command description
     *
     * @var string
     */
    protected $description = 'Restore a tenant.';

    /**
     * Console command signature
     *
     * @var string
     */
    protected $signature = 'orchestra:restore {name} {--driver= : Specify a database driver}';

    /**
     * Execute the console command.
     *
     * @return int
     *
     * @throws \Exception
     */
    public function handle(): int
    {
        try {
            // Récupérer les arguments et options correctement
            $name   = $this->argument('name');
            $driver = $this->option('driver');
            $driver = $driver ?? getDriver(base_path('.env'));

            // Vérification de la validité des données
            if (empty($name)) {
                runInConsole(fn () => $this->error('The tenant name is required.'));

                return Command::FAILURE;
            }

            // Affichage pour vérifier les valeurs
            runInConsole(fn () => $this->info("Restoring tenant: $name"));

            Oor::restore(parseTenantName($name), $driver, $this->output);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            runInConsole(function () use ($e) {
                $this->error($e->getMessage());

                return Command::FAILURE;
            });
            throw new \Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
