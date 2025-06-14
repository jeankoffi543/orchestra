<?php

namespace Kjos\Orchestra\Facades;

use Closure;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Application;

class Orchestra extends OperaBuilder
{
    protected Application $app;

    public function __construct(Application $app)
    {
        parent::__construct();
        $this->app = $app;
    }

    /**
     * @param array<string, mixed> $data
     * @param string|null $driver
     * @param bool $migrate
     * @return void
     */
    public function create(array $data, ?string $driver = 'pgsql', bool $migrate = true): void
    {
        $this->createTenant($data, $driver, $migrate);
    }

    public function delete(string $name, ?string $driver = 'pgsql'): void
    {
        $this->deleteTenant($name, $driver);
    }

    /**
     * Update a tenant.
     *
     * @param array<string, mixed> $data tenant data
     * @param string|null $driver database driver
     * @return void
     */
    public function update(array $data, ?string $driver = 'pgsql'): void
    {
        $this->updateTenant($data, $driver);
    }

    public function use(string $name, Closure $callback): void
    {
        $this->useTenant($name, $callback);
    }

    public function switch(string $name): void
    {
        $this->switchToTenant($name);
    }

    public function initialize(): void
    {
        $this->switchToCurrent();
    }

    /**
     * Return the list of tenants.
     *
     * @return array<string, string> tenant name as key and tenant data as value
     */
    public function getTenants(): array
    {
        return $this->listTenants();
    }

    public function restore(string $tenantName, string $driver = 'pgsql', ?OutputStyle $console = null): void
    {
        $this->restoreTenant($tenantName, $driver, $console);
    }
}
