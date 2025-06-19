<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Kjos\Orchestra\Services\Deployer;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'orchestra:remove:deployer')]
class RemoveDeployerCommand extends Command
{
    /**
     * Console command name
     *
     * @var string
     */
    protected $name = 'orchestra:remove:deployer';

    /**
     * Console command description
     *
     * @var string
     */
    protected $description = 'Remove a deployer.';

    /**
     * Console command signature
     *
     * @var string
     */
    protected $signature = 'orchestra:remove:deployer';

    public function handle(): int
    {
        try {
            if (! app()->runningInConsole()) {
                return Command::SUCCESS;
            }
            $deployer = new Deployer();
            $deployer->reset();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Erreur : ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
