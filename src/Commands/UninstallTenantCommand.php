<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Response;
use Kjos\Orchestra\Facades\Concerns\Installer;
use Kjos\Orchestra\Facades\Concerns\RollbackManager;
use Kjos\Orchestra\Facades\Oor;
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
    protected $signature = 'orchestra:uninstall {name : master tenant name} {--driver= : master tenant database driver}';

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
        $rollback = new RollbackManager();

        try {
            $installer = new Installer();
            runInConsole(fn () => $this->info('Uninstalling Orchestra...'));
            // directory initialisation
            $driver = $this->option('driver');
            $driver = $this->option('driver');
            $master = $this->argument('name');
            $driver = $driver ?? getDriver(base_path('.env'));

            //check if is master
            if (! Oor::isMaster($master)) {
                runInConsole(fn () => $this->error('This is not the master tenant'));

                return Command::FAILURE;
            }

            $installer->prepareUnInstallation($master, $driver, $rollback, $this->output);

            // remove deployer
            $this->call('orchestra:virtualhosts:remove');

            $this->call('orchestra:remove:deployer');

            $this->call('schedule:clear-cache');

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
