<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Response;
use Kjos\Orchestra\Services\VritualHost;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'orchestra:virtualhost:scanner')]
class MakeVirtualHostScanCommand extends Command
{
    /**
     * Console command name
     *
     * @var string
     */
    protected $name = 'orchestra:virtualhost:scanner';

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
    protected $signature = 'orchestra:virtualhost:scanner';

    public function handle(): int
    {
        try {
            if (! app()->runningInConsole()) {
                return Command::SUCCESS;
            }

            $virtualHost = new VritualHost(null, null);
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
