<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Kjos\Orchestra\Facades\Oor;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'orchestra:reload')]
class ReloadDomainCommand extends Command
{
    /**
     * Console command name
     *
     * @var string
     */
    protected $name = 'orchestra:reload';

    /**
     * Console command description
     *
     * @var string
     */
    protected $description = 'Create a new tenant.';

    /**
     * Console command signature
     *
     * @var string
     */
    protected $signature = 'orchestra:reload {name?}';

    public function handle(): int
    {
        try {
            Oor::getCurrent();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            \runInConsole(fn () => $this->error($e->getMessage()));

            return Command::FAILURE;
        }
    }
}
