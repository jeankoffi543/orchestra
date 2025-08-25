<?php

namespace Kjos\Orchestra\Contexts;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Kjos\Orchestra\Facades\Concerns\Tenancy;

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
        $tenantEnv = [];

        if (File::exists($envPath = base_path("site/{$tenant}/.env"))) {
            $tenantEnv = \parse_ini_file($envPath);
        }

        // Environnement de test : override avec toutes les variables de phpunit.xml
        if (app()->environment('testing')) {
            // Priorité aux variables définies dans phpunit.xml (elles sont dans $_ENV)
            // Fusion : phpunit.xml > .env du tenant
            $env = \array_merge($tenantEnv, [
                'APP_URL'                => env('APP_URL'),
                'APP_ENV'                => env('APP_ENV'),
                'DB_CONNECTION'          => env('DB_CONNECTION'),
                'DB_PORT'                => env('DB_PORT'),
                'DB_DATABASE'            => env('DB_DATABASE'),
                'DB_USERNAME'            => env('DB_USERNAME'),
                'DB_PASSWORD'            => env('DB_PASSWORD'),
                'APP_KEY'                => env('APP_KEY'),
                'QUEUE_CONNECTION'       => env('QUEUE_CONNECTION'),
                'SESSION_DRIVER'         => env('SESSION_DRIVER'),
                'CACHE_STORE'            => env('CACHE_STORE'),
                'APP_MAINTENANCE_DRIVER' => env('APP_MAINTENANCE_DRIVER'),
                'BCRYPT_ROUNDS'          => env('BCRYPT_ROUNDS'),
                'MAIL_MAILER'            => env('MAIL_MAILER'),
                'PULSE_ENABLED'          => env('PULSE_ENABLED'),
                'TELESCOPE_ENABLED'      => env('TELESCOPE_ENABLED'),
            ]);
        } else {
            $env = $tenantEnv;
        }

        $config = [
            // pgsql
            'database.connections.pgsql.driver'   => 'pgsql',
            'database.connections.pgsql.host'     => $env['DB_HOST']     ?? env('DB_HOST', '127.0.0.1'),
            'database.connections.pgsql.port'     => $env['DB_PORT']     ?? env('DB_PORT', '5432'),
            'database.connections.pgsql.database' => $env['DB_DATABASE'] ?? env('DB_DATABASE', 'forge'),
            'database.connections.pgsql.username' => $env['DB_USERNAME'] ?? env('DB_USERNAME', 'forge'),
            'database.connections.pgsql.password' => $env['DB_PASSWORD'] ?? env('DB_PASSWORD', ''),

            // pgsql test
            'database.connections.pgsql_test.driver'   => 'pgsql',
            'database.connections.pgsql_test.host'     => $env['DB_HOST']     ?? env('DB_HOST', '127.0.0.1'),
            'database.connections.pgsql_test.port'     => $env['DB_PORT']     ?? env('DB_PORT', '5432'),
            'database.connections.pgsql_test.database' => $env['DB_DATABASE'] ?? env('DB_DATABASE', 'forge'),
            'database.connections.pgsql_test.username' => $env['DB_USERNAME'] ?? env('DB_USERNAME', 'forge'),
            'database.connections.pgsql_test.password' => $env['DB_PASSWORD'] ?? env('DB_PASSWORD', ''),

            // mysql
            'database.connections.mysql.driver'   => 'mysql',
            'database.connections.mysql.host'     => $env['DB_HOST']     ?? env('DB_HOST', '127.0.0.1'),
            'database.connections.mysql.port'     => $env['DB_PORT']     ?? env('DB_PORT', '3306'),
            'database.connections.mysql.database' => $env['DB_DATABASE'] ?? env('DB_DATABASE', 'forge'),
            'database.connections.mysql.username' => $env['DB_USERNAME'] ?? env('DB_USERNAME', 'forge'),
            'database.connections.mysql.password' => $env['DB_PASSWORD'] ?? env('DB_PASSWORD', ''),

            // mysql test
            'database.connections.mysql_test.driver'   => 'mysql',
            'database.connections.mysql_test.host'     => $env['DB_HOST']     ?? env('DB_HOST', '127.0.0.1'),
            'database.connections.mysql_test.port'     => $env['DB_PORT']     ?? env('DB_PORT', '3306'),
            'database.connections.mysql_test.database' => $env['DB_DATABASE'] ?? env('DB_DATABASE', 'forge'),
            'database.connections.mysql_test.username' => $env['DB_USERNAME'] ?? env('DB_USERNAME', 'forge'),
            'database.connections.mysql_test.password' => $env['DB_PASSWORD'] ?? env('DB_PASSWORD', ''),

            // sqlite
            'database.connections.sqlite.driver'   => 'sqlite',
            'database.connections.sqlite.database' => base_path("site/{$tenant}/storage/database/database.sqlite"),

            // sqlite test
            'database.connections.sqlite_test.driver'   => 'sqlite',
            'database.connections.sqlite_test.database' => base_path("site/{$tenant}/storage/database/database.sqlite"),

            // sqlsrv
            'database.connections.sqlsrv.driver'   => 'sqlsrv',
            'database.connections.sqlsrv.host'     => $env['DB_HOST']     ?? env('DB_HOST', '127.0.0.1'),
            'database.connections.sqlsrv.port'     => $env['DB_PORT']     ?? env('DB_PORT', '1433'),
            'database.connections.sqlsrv.database' => $env['DB_DATABASE'] ?? env('DB_DATABASE', 'forge'),
            'database.connections.sqlsrv.username' => $env['DB_USERNAME'] ?? env('DB_USERNAME', 'forge'),
            'database.connections.sqlsrv.password' => $env['DB_PASSWORD'] ?? env('DB_PASSWORD', ''),

            // sqlsrv test
            'database.connections.sqlsrv_test.driver'   => 'sqlsrv',
            'database.connections.sqlsrv_test.host'     => $env['DB_HOST']     ?? env('DB_HOST', '127.0.0.1'),
            'database.connections.sqlsrv_test.port'     => $env['DB_PORT']     ?? env('DB_PORT', '1433'),
            'database.connections.sqlsrv_test.database' => $env['DB_DATABASE'] ?? env('DB_DATABASE', 'forge'),
            'database.connections.sqlsrv_test.username' => $env['DB_USERNAME'] ?? env('DB_USERNAME', 'forge'),
            'database.connections.sqlsrv_test.password' => $env['DB_PASSWORD'] ?? env('DB_PASSWORD', ''),


            'app.key' => $env['APP_KEY'] ?? 'base64:' . \base64_encode(\random_bytes(32)),

            'app.env' => $env['APP_ENV'] ?? 'production',
            'app.url' => $env['APP_URL'] ?? 'http://localhost',

            'filesystems.disks.local.root'  => base_path("site/{$tenant}/storage/app/private"),
            'filesystems.disks.public.root' => base_path("site/{$tenant}/storage/app/public"),
            'filesystems.disks.public.url'  => \rtrim($env['APP_URL'] ?? 'http://localhost', '/') . "/storage/{$tenant}",

            'queue.default'  => $env['QUEUE_CONNECTION'] ?? env('QUEUE_CONNECTION'),
            'cache.default'  => $env['CACHE_STORE']      ?? env('CACHE_STORE'),
            'session.driver' => $env['SESSION_DRIVER']   ?? env('SESSION_DRIVER'),

            'database.migrations_paths' => [OrchestraPath::migrations($tenant)],

            // etc... toutes les configs utiles à ton app
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
        $env = [];
        if (File::exists($env = base_path('.env'))) {
            $env = \parse_ini_file($env);
        }
        $config = [
            'database.connections.pgsql.database' => $env['DB_DATABASE'] ?? 'forge',
            'database.connections.pgsql.username' => $env['DB_USERNAME'] ?? 'forge',
            'database.connections.pgsql.password' => $env['DB_PASSWORD'] ?? '',
            'filesystems.disks.local.root'        => storage_path('app/private'),
            'filesystems.disks.public.root'       => storage_path('app/public'),
            'filesystems.disks.public.url'        => env('APP_URL') . '/storage',


            // etc... toutes les configs utiles à ton app

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
     * @param \Closure(TenantContext): mixed $callback The callback function that receives the tenant context.
     * @return mixed The result of the callback function.
     */
    public function runFor(string $tenant, \Closure $callback): mixed
    {
        // force host
        $request = request();
        $request->headers->set('host', Tenancy::getDomain($tenant));

        $this->switchTo($tenant);
        Log::info('config = ' . config('database.connections.pgsql.database'));
        Log::info('🎯 Callback: DB courante = ' . DB::connection()->getDatabaseName());

        return $callback($this->current);

        // $this->rebase($tenant);

        // return $result;
    }
}
