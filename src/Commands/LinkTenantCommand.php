<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
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
            $target = base_path("site/$tenant/storage/app/public");
            $link   = public_path("storage/tenants/{$tenant}");

            forceSymlink($target, $link, $this->option('force'));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            runInConsole(function () use ($e) {
                $this->error($e->getMessage());
            });

            return Command::FAILURE;
        }
    }
}
