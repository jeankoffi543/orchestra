<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Response;
use Kjos\Orchestra\Facades\Concerns\Installer;
use Kjos\Orchestra\Facades\Concerns\RollbackManager;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'orchestra:install')]
class InstallTenantCommand extends Command
{
    /**
     * Console command name
     *
     * @var string
     */
    protected $name = 'orchestra:install';

    /**
     * Console command description
     *
     * @var string
     */
    protected $description = 'Install the package';

    /**
     * Console command signature
     *
     * @var string
     */
    protected $signature = 'orchestra:install  {master} {--domain= : master tenant domain} {--driver= : master tenant database driver}';

    /**
     * Execute the console command.
     *
     * @return int
     *
     * @throws \Exception
     */
    public function handle(): int
    {
        $rollback = new RollbackManager();

        try {
            if (! $master = $this->argument('master')) {
                throw new \Exception('master tenant name is required', Response::HTTP_BAD_REQUEST);
            }
            if (! $domain = $this->option('domain')) {
                throw new \Exception('master tenant domain is required', Response::HTTP_BAD_REQUEST);
            }
            $driver = $this->option('driver');
            $driver = $driver ?? getDriver(base_path('.env'));

            $installer = new Installer();
            runInConsole(fn () => $this->info('Installing Orchestra...'));

            // directory initialisation and master tenant creation
            $installer->prepareInstallation(parseTenantName($master), $domain, $driver, $rollback, $this->output);

            // run deployer
            $this->call('orchestra:create:deployer');

            $this->call('schedule:clear-cache');

            runInConsole(fn () => $this->info('Installation complete'));

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
