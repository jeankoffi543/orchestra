<?php

namespace Kjos\Orchestra\Contexts;

use Closure;

class TenantManager
{
    protected ?TenantContext $current = null;

    /**
     * Switches the application context to a specified tenant.
     *
     * This function reads the specific .env file of the given tenant to configure
     * environment variables and constructs an isolated configuration for the tenant.
     * It sets up file system paths and database connections specific to the tenant,
     * and applies the configuration in memory without altering global environment
     * variables.
     *
     * @param string $tenant The name of the tenant to switch to.
     */
    public function switchTo(string $tenant): void
    {
        // Lire .env spécifique sans toucher à $_ENV
        $env = \parse_ini_file(base_path("site/{$tenant}/.env"));

        // Construire la config isolée
        $config = [
            'database.connections.pgsql.database' => $env['DB_DATABASE'] ?? 'forge',
            'database.connections.pgsql.username' => $env['DB_USERNAME'] ?? 'forge',
            'database.connections.pgsql.password' => $env['DB_PASSWORD'] ?? '',
            'filesystems.disks.local.root'        => base_path("site/{$tenant}/storage/app/private"),
            'filesystems.disks.public.root'       => base_path("site/{$tenant}/storage/app/public"),
            'filesystems.disks.public.url'        => env('APP_URL') . "/storage/{$tenant}",

            // etc.
        ];

        $this->current = new TenantContext($tenant, $env, $config);
        $this->current->apply();
    }

    /**
     * Restore the default (non-tenant) environment variables.
     *
     * @param string|null $tenant
     */
    public function rebase(?string $tenant = null): void
    {
        $env    = \parse_ini_file(base_path('.env'));
        $config = [
            'database.connections.pgsql.database' => $env['DB_DATABASE'] ?? 'forge',
            'database.connections.pgsql.username' => $env['DB_USERNAME'] ?? 'forge',
            'database.connections.pgsql.password' => $env['DB_PASSWORD'] ?? '',
            'filesystems.disks.local.root'        => storage_path('app/private'),
            'filesystems.disks.public.root'       => storage_path('app/public'),
            'filesystems.disks.public.url'        => env('APP_URL') . '/storage',
            // etc.
        ];

        $this->current = new TenantContext($tenant, $env, $config);
        $this->current->apply();
    }

    /**
     * Retrieves the current tenant context.
     *
     * @return TenantContext|null Returns the current TenantContext if set, or null if not.
     */
    public function current(): ?TenantContext
    {
        return $this->current;
    }

    /**
     * Switches to the specified tenant and executes a callback function within the tenant's context.
     *
     * @param string $tenant The tenant to switch to.
     * @param \Closure $callback The callback function to execute within the tenant's context.
     * @return Closure The result of the callback function.
     */
    public function runFor(string $tenant, \Closure $callback): Closure
    {
        $this->switchTo($tenant);

        return $callback($this->current);
    }
}
