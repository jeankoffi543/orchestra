<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Response;
use Kjos\Orchestra\Services\VritualHost;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'orchestra:virtualhosts:remove')]
class RemoveVirtualHostsCommand extends Command
{
    /**
     * Console command name
     *
     * @var string
     */
    protected $name = 'orchestra:virtualhosts:remove';

    /**
     * Console command description
     *
     * @var string
     */
    protected $description = 'remove virtual hosts';

    /**
     * Console command signature
     *
     * @var string
     */
    protected $signature = 'orchestra:virtualhosts:remove 
    {name? : Name of the host} 
    {--domain= : Domain name of the host}';

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
            if (! app()->runningInConsole()) {
                return Command::SUCCESS;
            }
            $name   = $this->argument('name');
            $domain = $this->option('domain');

            if ($name && $domain) {
                $virtualHost = new VritualHost($name, $domain);
                $virtualHost->remove($domain);
            } else {
                $virtualHost = new VritualHost();
                $hosts       = $virtualHost->vhostSites();
                foreach ($hosts as $hostDomain) {
                    $virtualHost->remove($hostDomain);
                }
            }

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
