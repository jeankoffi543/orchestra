<?php

namespace Kjos\Orchestra\Facades;

use Closure;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Application;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Kjos\Orchestra\Facades\Concerns\RollbackManager;
use Kjos\Orchestra\Facades\Concerns\Tenancy;
use Kjos\Orchestra\Services\TenantDatabaseManager;

class OperaBuilder
{
    protected Application $app;
    protected string $stubPasth;
    protected string $sitePath;
    protected string $composerPath;
    protected string $moduleSitePath;
    protected string $moduleStubPath;

    public function __construct()
    {
        $this->init();
    }

    /**
     * Create a new tenant.
     *
     * @param array<string, mixed> $data An associative array containing tenant data, including 'name' and 'domains'.
     * @param string|null $driver The database driver to be used, default is 'pgsql'.
     * @param bool $migrate Whether to migrate the tenant's database schema, default is true.
     * @return void
     */
    protected function createTenant(array $data, ?string $driver = 'pgsql', bool $migrate = true): void
    {
        Tenancy::createTenant($data, $driver, $migrate);
    }

    /**
     * Create a new testing tenant.
     *
     * This function creates a new tenant for testing purposes using the provided data.
     * It validates the tenant data, generates database credentials, and configures
     * the tenant environment without migrating the database schema.
     *
     * @param array<string, mixed> $data An associative array containing tenant data,
     *                                   including 'name' and 'domains'.
     * @return void
     */
    public function createTesting(array $data): void
    {
        Tenancy::createTenantTesting($data);
    }

    /**
     * Clean up a tenant used for testing.
     *
     * This function removes all files and directories associated with the given tenant,
     * including its storage, site directory, and database credentials.
     * It also removes the tenant's domain from the tenants file.
     *
     * @param string $anme The name of the tenant to clean up.
     * @param string $domain The domain of the tenant to remove from the tenants file.
     * @return void
     */
    public function cleartenantTesting(string $anme, string $domain): void
    {
        Tenancy::cleartenantTesting($anme, $domain);
    }

    protected function deleteTenant(string $name, ?string $driver = 'pgsql', ?string $domain = ''): void
    {
        Tenancy::deleteTenant($name, $driver, $domain);
    }

    /**
     * Update an existing tenant's information.
     *
     * @param array<string, mixed> $data An associative array containing the updated tenant data,
     *                                   including the 'name' of the tenant and any other fields to update.
     * @return void
     */
    protected function updateTenant(array $data): void
    {
        Tenancy::updateTenant($data);
    }

    /**
     * Switch to a tenant.
     *
     * This function switches the current tenant context to the given tenant name.
     * If no tenant name is provided, it will switch to the default tenant.
     *
     * @param string|null $name The name of the tenant to switch to, or null to switch to the default tenant.
     * @return void
     */
    protected function switchToTenant(?string $name = null): void
    {
        Tenancy::switchToTenant($name);
    }

    /**
     * Execute a given callback within the context of a specified tenant.
     *
     * This function temporarily switches the application's context to the
     * specified tenant, executes the provided callback, and then reverts
     * back to the original context.
     *
     * @param string $name The tenant's name to switch context to.
     * @param Closure $callback The callback to execute within the tenant's context.
     * @return void
     *
     * @throws \Exception If the tenant is not found or if an error occurs while switching the context.
     */
    protected function useTenant(string $name, Closure $callback): void
    {
        Tenancy::useTenant($name, $callback);
    }

    /**
     * Initialize the Orchestra class by setting the .stub path and site path.
     *
     * It also creates the site/.tenants file if it does not exist.
     *
     * @return void
     *
     * @throws \Exception If an error occurs while initializing the class.
     */
    private function init(): void
    {
        // .stub
        try {
            TenantDatabaseManager::connect();
            $this->sitePath       = getBasePath();
            $this->stubPasth      = getStubPath();
            $this->moduleStubPath = __DIR__ . '/../../.stub';
            Tenancy::init(__DIR__ . '/../../.stub');

            //create site/.tenants file
            if (!File::exists($this->sitePath)) {
                File::makeDirectory($this->sitePath);
                File::put("$this->sitePath/.tenants", "# Registered tenants\n\n");
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get the current tenant.
     *
     * @return string|null The current tenant's name, or null if no tenant is set.
     */
    public function getCurrent(): ?string
    {
        return Tenancy::getCurrent();
    }

    /**
     * Switch to the current tenant.
     *
     * This function changes the tenant context to the current tenant
     * based on the current request domain.
     *
     * @return void
     */
    public function switchToCurrent(): void
    {
        Tenancy::switchToTenant(Tenancy::getCurrent());
    }

    /**
     * Run the given callback in the context of the current tenant.
     *
     * This function determines the current tenant using the request's domain
     * and runs the given callback in the context of that tenant.
     *
     * @param Closure $callback The callback to run in the context of the current tenant.
     * @return void
     */
    public function useCurrent(Closure $callback): void
    {
        Tenancy::useTenant(Tenancy::getCurrent(), $callback);
    }

    /**
     * Migrate a tenant.
     *
     * @param array<string, mixed> $credentials The database credentials for the tenant.
     * @param string $name The name of the tenant to migrate.
     * @param RollbackManager $rollback The rollback manager to use for the migration.
     * @param bool $exists Whether the tenant already exists.
     * @return void
     */
    public function migrate(array $credentials, string $name, RollbackManager $rollback, bool $exists): void
    {
        Tenancy::migrate($credentials, $name, $rollback, $exists);
    }

    /**
     * Restore a tenant that was previously backed up and archived.
     *
     * @param string $tenantName The name of the tenant to restore.
     * @param string $driver The database driver to use for the tenant's database.
     * @param OutputStyle $console An optional console object to use for output.
     * @return void
     */
    public function restoreTenant(string $tenantName, string $driver = 'pgsql', ?OutputStyle $console = null): void
    {
        Tenancy::restore($tenantName, $driver, $console);
    }

    /**
     * Retrieve the list of tenants.
     *
     * @return array<string, string> An array of tenant names.
     */
    public function listTenants(): array
    {
        return Tenancy::listTenants();
    }
}
