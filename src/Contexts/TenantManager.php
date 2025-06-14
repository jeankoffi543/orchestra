<?php

namespace Kjos\Orchestra\Contexts;

use Closure;

class TenantManager
{
    protected ?TenantContext $current = null;

    public function switchTo(string $tenant): void
    {
        // Lire .env spécifique sans toucher à $_ENV
        $env = \parse_ini_file(\base_path("site/{$tenant}/.env"));

        // Construire la config isolée
        $config = [
            'database.connections.pgsql.database' => $env['DB_DATABASE'] ?? 'forge',
            'database.connections.pgsql.username' => $env['DB_USERNAME'] ?? 'forge',
            'database.connections.pgsql.password' => $env['DB_PASSWORD'] ?? '',
            'filesystems.disks.local.root'        => \base_path("site/{$tenant}/storage/app/private"),
            'filesystems.disks.public.root'       => \base_path("site/{$tenant}/storage/app/public"),
            'filesystems.disks.public.url'        => \env('APP_URL') . "/storage/{$tenant}",

            // etc.
        ];

        $this->current = new TenantContext($tenant, $env, $config);
        $this->current->apply();
    }

    public function rebase(?string $tenant = null): void
    {
        $env    = \parse_ini_file(\base_path('.env'));
        $config = [
            'database.connections.pgsql.database' => $env['DB_DATABASE'] ?? 'forge',
            'database.connections.pgsql.username' => $env['DB_USERNAME'] ?? 'forge',
            'database.connections.pgsql.password' => $env['DB_PASSWORD'] ?? '',
            'filesystems.disks.local.root'        => \storage_path('app/private'),
            'filesystems.disks.public.root'       => \storage_path('app/public'),
            'filesystems.disks.public.url'        => \env('APP_URL') . '/storage',
            // etc.
        ];

        $this->current = new TenantContext($tenant, $env, $config);
        $this->current->apply();
    }

    public function current(): ?TenantContext
    {
        return $this->current;
    }

    public function runFor(string $tenant, \Closure $callback): Closure
    {
        $this->switchTo($tenant);

        return $callback($this->current);
    }
}
