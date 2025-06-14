<?php

namespace Kjos\Orchestra\Facades;

use Closure;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Application;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
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

    protected function deleteTenant(string $name, ?string $driver = 'pgsql', ?bool $rollback = false): void
    {
        Tenancy::deleteTenant($name, $driver, $rollback);
    }

    /**
     * Update an existing tenant's information.
     *
     * @param array<string, mixed> $data An associative array containing the updated tenant data,
     *                                   including the 'name' of the tenant and any other fields to update.
     * @param string|null $driver The database driver to be used, default is 'pgsql'.
     * @return void
     */
    protected function updateTenant(array $data, ?string $driver = 'pgsql'): void
    {
        Tenancy::updateTenant($data, $driver);
    }

    protected function switchToTenant(?string $name = null): void
    {
        Tenancy::switchToTenant($name);
    }

    protected function useTenant(string $name, Closure $callback): void
    {
        Tenancy::useTenant($name, $callback);
    }

    private function init(): void
    {
        // .stub
        try {
            TenantDatabaseManager::connect();
            $this->sitePath       = \getBasePath();
            $this->stubPasth      = \getStubPath();
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

    public function getCurrent(): ?string
    {
        return Tenancy::getCurrent();
    }

    public function switchToCurrent(): void
    {
        Tenancy::switchToTenant(Tenancy::getCurrent());
    }

    public function useCurrent(Closure $callback): void
    {
        Tenancy::useTenant(Tenancy::getCurrent(), $callback);
    }

    /**
     * Run the database migrations for the given tenant.
     *
     * @param array<string, mixed> $credentials The database credentials for the tenant.
     * @param string $name The name of the tenant.
     * @return void
     */
    public function migrate(array $credentials, string $name): void
    {
        Tenancy::migrate($credentials, $name);
    }

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
