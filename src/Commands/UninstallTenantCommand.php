<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Response;
use Kjos\Orchestra\Facades\Concerns\Installer;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'orchestra:uninstall')]
class UninstallTenantCommand extends Command
{
    /**
     * Console command name
     *
     * @var string
     */
    protected $name = 'orchestra:uninstall';

    /**
     * Console command description
     *
     * @var string
     */
    protected $description = 'Uninstall the package';

    /**
     * Console command signature
     *
     * @var string
     */
    protected $signature = 'orchestra:uninstall {master} {--driver= : master tenant database driver}';

    /**
     * Execute the console command.
     *
     * @return int
     */
    /**
     * @throws \Exception
     */
    public function handle(): int
    {
        try {
            if (! $master = $this->argument('master')) {
                throw new \Exception('master tenant name is required', Response::HTTP_BAD_REQUEST);
            }

            $installer = new Installer();
            runInConsole(fn () => $this->info('Uninstalling Orchestra...'));
            // directory initialisation
            $driver = $this->option('driver');
            $driver = $driver ?? getDriver(base_path('.env'));
            $installer->prepareUnInstallation($master, $driver, $this->output);

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
