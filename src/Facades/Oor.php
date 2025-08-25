<?php

namespace Kjos\Orchestra\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void create(array<string, mixed> $data, ?string $driver = 'pgsql', bool $migrate = true)
 * @method static void createTesting(array<string, mixed> $data)
 * @method static void delete(string $name, ?string $driver = 'pgsql', ?string $domain = '')
 * @method static void cleartenantTesting(string $name, string $domain)
 * @method static void update(array<string, mixed> $data)
 * @method static void switch(string $tenant)
 * @method static void use(string $name, \Closure $callback)
 * @method static void reloadDomain()
 * @method static ?string getCurrent()
 * @method static void setCurrent(string $name)
 * @method static void switchToCurrent()
 * @method static void useCurrent(\Closure $callback)
 * @method static void initialize()
 * @method static void migrate(array<string, mixed> $credentials, string $name, \Kjos\Orchestra\Facades\Concerns\RollbackManager $rollback, bool $exists = true)
 * @method static void restore(string $tenantName, string $driver = 'pgsql', \Illuminate\Console\OutputStyle $console)
 * @method static array<string, string> getTenants()
 * @method static bool isMaster(?string $name = null, bool $force = false)
 * @method static void runFor(string $name, \Closure $callback)
 * @see \App\Providers\Facades\Orchestra
 */

class Oor extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'oor';
    }
}
