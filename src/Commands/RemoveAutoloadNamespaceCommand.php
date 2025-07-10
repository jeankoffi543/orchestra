<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class RemoveAutoloadNamespaceCommand extends Command
{
    protected $signature   = 'orchestra:autoload:remove {name : Nom du tenant}';
    protected $description = 'Supprime les mappings PSR-4 du tenant dans composer.json et recharge l’autoload.';

    /**
     * Handle the command to remove PSR-4 namespace mappings for a tenant from composer.json
     * and regenerate the autoload files.
     *
     * Retrieves the tenant name from the command arguments and checks if the composer.json
     * file exists. If not, it logs an error and returns a failure status.
     *
     * It then checks for the existence of the PSR-4 autoload section and attempts to remove
     * the specific namespace mappings related to the tenant. If no mappings are found, it logs
     * a warning and returns a success status.
     *
     * If mappings are removed, the updated composer.json file is saved, and the autoload files
     * are regenerated using composer dump-autoload command.
     *
     * @return int Command status code indicating success or failure.
     * @throws \Exception If any error occurs during the process.
     */
    public function handle(): int
    {
        try {
            $name         = $this->argument('name');
            $composerPath = base_path('composer.json');
            $nameStudy    = Str::studly($name);

            if (!\file_exists($composerPath)) {
                runInConsole(fn () => $this->error('composer.json introuvable.'));

                return Command::FAILURE;
            }

            $composer = \json_decode(\file_get_contents($composerPath), true);

            if (!isset($composer['autoload']['psr-4'])) {
                runInConsole(fn () => $this->warn('Aucune section psr-4 trouvée.'));

                return Command::SUCCESS;
            }

            $psr4       = &$composer['autoload']['psr-4'];
            $namespaces = [
                "Site\\{$nameStudy}\\App\\",
                "Site\\{$nameStudy}\\Database\\Factories\\",
                "Site\\{$nameStudy}\\Database\\Seeders\\",
            ];

            $modified = false;

            foreach ($namespaces as $namespace) {
                if (isset($psr4[$namespace])) {
                    unset($psr4[$namespace]);
                    $modified = true;
                    runInConsole(fn () => $this->info("Namespace supprimé : $namespace"));
                }
            }

            if (! $modified) {
                runInConsole(fn () => $this->warn("Aucun mapping trouvé pour le tenant [$name]."));

                return Command::SUCCESS;
            }

            // Sauvegarde
            \file_put_contents($composerPath, \json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            runInConsole(fn () => $this->info('composer.json mis à jour.'));

            // Dump autoload
            runInConsole(fn () => $this->info('Exécution de composer dump-autoload...'));
            \exec('composer dump-autoload');

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
