<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Response;
use Kjos\Orchestra\Services\VritualHost;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'orchestra:make:virtualhost')]
class MakeVirtualHostCommand extends Command
{
    /**
     * Console command name
     *
     * @var string
     */
    protected $name = 'orchestra:make:virtualhost';

    /**
     * Console command description
     *
     * @var string
     */
    protected $description = 'Create a new virtual host.';

    /**
     * Console command signature
     *
     * @var string
     */
    protected $signature = 'orchestra:make:virtualhost {name : The name of the tenant} {domain= : The domain of the tenant}';

    public function handle(): int
    {
        try {
            if (! app()->runningInConsole()) {
                return Command::SUCCESS;
            }
            $name        = $this->argument('name');
            $domain      = $this->argument('domain');
            $virtualHost = new VritualHost($name, $domain);
            $virtualHost->scanner();

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
