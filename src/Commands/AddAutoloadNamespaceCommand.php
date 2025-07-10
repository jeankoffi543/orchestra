<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class AddAutoloadNamespaceCommand extends Command
{
    protected $signature   = 'orchestra:autoload:add {tenant}';
    protected $description = 'Ajoute les namespaces PSR-4 du tenant au composer.json du projet';

    /**
     * Handle the command.
     *
     * @return int
     *
     * @throws \Exception
     */
    public function handle(): int
    {
        try {
            $tenant       = $this->argument('tenant');
            $composerPath = base_path('composer.json');
            $tenantStudy  = Str::studly($tenant);

            if (!\file_exists($composerPath)) {
                runInConsole(fn () => $this->error('composer.json introuvable.'));
                throw new \Exception('composer.json introuvable.', Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $json = \json_decode(\file_get_contents($composerPath), true);

            // Initialiser la structure si absente
            $json['autoload']          ??= [];
            $json['autoload']['psr-4'] ??= [];

            // Ajouter les entrées spécifiques
            $autoloads = [
                "Site\\$tenantStudy\\App\\"                 => "site/$tenant/app/",
                "Site\\$tenantStudy\\Database\\Factories\\" => "site/$tenant/database/factories/",
                "Site\\$tenantStudy\\Database\\Seeders\\"   => "site/$tenant/database/seeders/",
            ];

            foreach ($autoloads as $ns => $path) {
                if (!isset($json['autoload']['psr-4'][$ns])) {
                    $json['autoload']['psr-4'][$ns] = $path;
                    runInConsole(fn () => $this->info("Ajouté: $ns → $path"));
                } else {
                    runInConsole(fn () => $this->line("Déjà présent: $ns"));
                }
            }

            // Écriture du fichier mis à jour
            \file_put_contents($composerPath, \json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Dump autoload pour prise en compte
            \exec('composer dump-autoload');

            runInConsole(fn () => $this->info('composer.json mis à jour avec succès et autoload régénéré.'));

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
