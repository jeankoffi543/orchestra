<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Console\Migrations\BaseCommand;
use Kjos\Orchestra\Services\TenantDatabaseManager;

class DatabaseCreateCommand extends BaseCommand
{
    protected $signature = 'db:create {--database= : The database name to use.} {--connection= : The database connection to use.}';

    public function handle(): int
    {
        $database   = $this->option('database')   ?? env('DB_DATABASE');
        $connection = $this->option('connection') ?? env('DB_CONNECTION', 'pgsql');
        $driver     = env('DB_DRIVER', 'pgsql');
        $username   = env('DB_USERNAME');
        // $password = env('DB_PASSWORD');

        TenantDatabaseManager::connect(driver: $driver, connection: $connection);
        TenantDatabaseManager::createDatabase($database, $username);

        return Command::SUCCESS;
    }
}
