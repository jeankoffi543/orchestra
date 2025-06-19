<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'orchestra:unlink')]
class UnlinkTenantCommand extends Command
{
    /**
     * Console command name
     *
     * @var string
     */
    protected $name = 'orchestra:unlink';

    /**
     * Console command description
     *
     * @var string
     */
    protected $description = 'Unlink a tenant from the application public directory.';

    /**
     * Console command signature
     *
     * @var string
     */
    protected $signature = 'orchestra:unlink {tenant}';

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

            $unlink = public_path("storage/tenants/{$tenant}");

            unlinSymlink($unlink);


            return Command::SUCCESS;
        } catch (\Exception $e) {
            runInConsole(function () use ($e) {
                $this->error($e->getMessage());
            });

            return Command::FAILURE;
        }
    }
}
