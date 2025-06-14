<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'orchestra:link')]
class LinkTenantCommand extends Command
{
    /**
     * Console command name
     *
     * @var string
     */
    protected $name = 'orchestra:link';

    /**
     * Console command description
     *
     * @var string
     */
    protected $description = 'Link a tenant to the application public directory.';

    /**
     * Console command signature
     *
     * @var string
     */
    protected $signature = 'orchestra:link {tenant} {--force : Force the link if it already exists} ';

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
            $tenant = $this->argument('tenant'); // par exemple
            $target = getBasePath("$tenant/storage/app/public");

            $link = public_path("storage/tenants/{$tenant}");

            if (!File::exists($target)) {
                throw new \Exception("Le dossier de stockage du tenant n'existe pas.");
            }
            if (File::exists($link)) {
                if ($this->option('force')) {
                    \unlink($link);
                    \symlink($target, $link);
                    runInConsole(fn () => $this->info("Lien symbolique recréé : {$link} → {$target}"));
                } else {
                    throw new \Exception("Le lien existe déjà : {$link} (utilise --force pour le recréer)");
                }
            } else {
                \symlink($target, $link);
                runInConsole(fn () => $this->info("Lien symbolique créé : {$link} → {$target}"));
            }

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
