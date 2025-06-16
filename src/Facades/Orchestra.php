<?php

namespace Kjos\Orchestra\Facades;

use Closure;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Application;

class Orchestra extends OperaBuilder
{
    protected Application $app;

    /**
     * Initialize the Orchestra class by setting the .stub path and site path.
     *
     * It also creates the site/.tenants file if it does not exist.
     *
     * @param Application $app The Laravel application instance.
     *
     * @return void
     *
     * @throws \Exception If an error occurs while initializing the class.
     */
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

    /**
     * Delete a tenant.
     *
     * @param string $name The name of the tenant to delete.
     * @param string|null $driver The database driver to use for the tenant's database.
     *
     * @return void
     */
    public function delete(string $name, ?string $driver = 'pgsql', ?string $domain = ''): void
    {
        $this->deleteTenant($name, $driver, $domain);
    }

    /**
     * Update a tenant.
     *
     * @param array<string, mixed> $data tenant data
     * @return void
     */
    public function update(array $data): void
    {
        $this->updateTenant($data);
    }

    /**
     * Switch to a tenant, execute a callback, and then switch back to the previous tenant.
     *
     * @param string $name The name of the tenant to switch to.
     * @param Closure $callback A closure to execute while switched to the tenant.
     *
     * @return void
     */
    public function use(string $name, Closure $callback): void
    {
        $this->useTenant($name, $callback);
    }

    /**
     * Switch to a tenant.
     *
     * This function switches the current tenant context to the given tenant name.
     * If no tenant name is provided, it will switch to the default tenant.
     *
     * @param string $name The name of the tenant to switch to.
     *
     * @return void
     */
    public function switch(string $name): void
    {
        $this->switchToTenant($name);
    }

    /**
     * Initialize the Orchestra class.
     *
     * This function is called by the Laravel service provider to initialize the
     * Orchestra class. It switches the current tenant context to the default
     * tenant.
     *
     * @return void
     */
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

    /**
     * Restore a tenant from its backup.
     *
     * @param string $tenantName The name of the tenant to restore.
     * @param string $driver The database driver to use for the tenant's database.
     * @param OutputStyle|null $console The console output.
     *
     * @return void
     */
    public function restore(string $tenantName, string $driver = 'pgsql', ?OutputStyle $console = null): void
    {
        $this->restoreTenant($tenantName, $driver, $console);
    }
}
