<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Response;
use Kjos\Orchestra\Facades\Concerns\Installer;
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
    protected $signature = 'orchestra:install  {master} {--domain= : master tenant domain}';

    public function handle(): int
    {
        try {
            if (! $master = $this->argument('master')) {
                throw new \Exception('master tenant name is required', Response::HTTP_BAD_REQUEST);
            }
            if (! $domain = $this->option('domain')) {
                throw new \Exception('master tenant domain is required', Response::HTTP_BAD_REQUEST);
            }

            $installer = new Installer();
            \runInConsole(fn () => $this->info('Installing Orchestra...'));
            // directory initialisation
            $info = $installer->prepareInstallation($master, $domain);

            \runInConsole(fn () => $this->info($info));


            return Command::SUCCESS;
        } catch (\Exception $e) {
            \runInConsole(fn () => $this->error($e->getMessage()));

            return Command::FAILURE;
        }
    }
}
