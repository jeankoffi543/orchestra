<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Response;

class RemoveAutoloadNamespaceCommand extends Command
{
    protected $signature   = 'orchestra:autoload:remove {name : Nom du tenant}';
    protected $description = 'Supprime les mappings PSR-4 du tenant dans composer.json et recharge l’autoload.';

    public function handle(): int
    {
        try {
            $name         = $this->argument('name');
            $composerPath = \base_path('composer.json');

            if (!\file_exists($composerPath)) {
                \runInConsole(fn () => $this->error('composer.json introuvable.'));

                return Command::FAILURE;
            }

            $composer = \json_decode(\file_get_contents($composerPath), true);

            if (!isset($composer['autoload']['psr-4'])) {
                \runInConsole(fn () => $this->warn('Aucune section psr-4 trouvée.'));

                return Command::SUCCESS;
            }

            $psr4       = &$composer['autoload']['psr-4'];
            $namespaces = [
                "Site\\{$name}\\App\\",
                "Site\\{$name}\\Database\\Factories\\",
                "Site\\{$name}\\Database\\Seeders\\",
            ];

            $modified = false;

            foreach ($namespaces as $namespace) {
                if (isset($psr4[$namespace])) {
                    unset($psr4[$namespace]);
                    $modified = true;
                    \runInConsole(fn () => $this->info("Namespace supprimé : $namespace"));
                }
            }

            if (! $modified) {
                \runInConsole(fn () => $this->warn("Aucun mapping trouvé pour le tenant [$name]."));

                return Command::SUCCESS;
            }

            // Sauvegarde
            \file_put_contents($composerPath, \json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            \runInConsole(fn () => $this->info('composer.json mis à jour.'));

            // Dump autoload
            \runInConsole(fn () => $this->info('Exécution de composer dump-autoload...'));
            \exec('composer dump-autoload');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            \runInConsole(fn () => $this->error($e->getMessage()));
            throw new \Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
