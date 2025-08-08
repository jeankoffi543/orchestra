<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Kjos\Orchestra\Facades\Oor;

class OrchestraWorkCommand extends Command
{
    protected $name        = 'orchestra:queue:work';
    protected $description = 'Run the queue worker for all tenant once. Must be run in a cron job';
    protected $signature   = 'orchestra:queue:work';

    public function handle(): int
    {
        foreach (Oor::getTenants() as $tenant) {
            $this->info("<bg=blue>Tenant:</> {$tenant}");
            Artisan::call('queue:work', [
                '--once'    => true,
                '--tenants' => [$tenant],
            ]);
        }

        return Command::SUCCESS;
    }
}
