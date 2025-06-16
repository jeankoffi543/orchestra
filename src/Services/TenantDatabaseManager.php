<?php

namespace Kjos\Orchestra\Services;

use Exception;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Validator;
use Kjos\Orchestra\Enums\ShellContextEnum;
use Kjos\Orchestra\Rules\DataBaseNameRule;
use Kjos\Orchestra\Rules\PassWordRule;
use Kjos\Orchestra\Rules\UserNameRule;
use PDO;

class TenantDatabaseManager
{
    private static ?string $driver;
    private static PDO $pdo;

    private static string $mainUserName;
    private static string $mainPassword;
    private static ?string $tenantName = '';

    /**
     * Connect to the database manager.
     *
     * @param string|null $driver The database driver to use. Supported drivers are 'pgsql', 'mysql', and 'sqlite'.
     * @param string|null $tenantName The name of the tenant to connect to.
     *
     * @throws \Exception If an unsupported driver is given.
     *
     * @return void
     */
    public static function connect(?string $driver = 'pgsql', ?string $tenantName = ''): void
    {
        self::$driver       = $driver;
        self::$tenantName   = $tenantName;
        self::$mainUserName = config('database.connections.pgsql.username');
        self::$mainPassword = config('database.connections.pgsql.password');

        switch ($driver) {
            case 'pgsql':
                self::$pdo = new PDO(
                    'pgsql:host=127.0.0.1;port=5432',
                    self::$mainUserName, // user with CREATE/DROP DB & ROLE
                    self::$mainPassword,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                break;
            case 'mysql':
                self::$pdo = new PDO(
                    'mysql:host=127.0.0.1;port=3306',
                    self::$mainUserName, // user with CREATE/DROP DB
                    self::$mainPassword,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                break;
            case 'sqlite':
                self::$pdo = new PDO('sqlite::memory:'); // SQLite is file-based
                break;
            default:
                throw new Exception("Unsupported driver: {$driver}");
        }
    }

    /**
     * Create a database user.
     *
     * @param string $username The username to create.
     * @param string $password The password for the new user.
     *
     * @return void
     *
     * @throws \Exception If the user already exists or if there is a problem while creating the user.
     */
    public static function createUser(string $username, string $password): void
    {
        $Validator = Validator::make(
            [
                'password' => $password,
                'username' => $username,
            ],
            [
                'password' => new PassWordRule(),
                'username' => new UserNameRule(),
            ]
        );

        if ($Validator->fails()) {
            return;
        }

        if (self::$driver === 'sqlite') {
            return;
        } // No user mgmt in SQLite

        try {
            $sql = self::$driver === 'pgsql'
                ? "CREATE USER \"$username\" WITH PASSWORD '$password'"
                : "CREATE USER '$username'@'%' IDENTIFIED BY '$password'";

            self::$pdo->exec($sql);
        } catch (Exception $e) {
            if (!\str_contains($e->getMessage(), 'exists')) {
                throw $e;
            }
        }
    }

    /**
     * Create a database for a tenant.
     *
     * @param string $dbname The name of the database to create.
     * @param string $owner The owner of the database.
     *
     * @return void
     *
     * @throws \Exception If an error occurs while creating the database.
     */
    public static function createDatabase(string $dbname, string $owner): void
    {
        $tenantName = self::$tenantName;
        try {
            $Validator = Validator::make(
                [
                    'dbname' => $dbname,
                    'owner'  => $owner,
                ],
                [
                    'dbname' => new DataBaseNameRule(),
                    'owner'  => new UserNameRule(),
                ]
            );

            if ($Validator->fails()) {
                return;
            }

            if (self::$driver === 'sqlite') {
                $path = base_path("site/$tenantName/database.sqlite");
                \touch($path);

                return;
            }

            $sql = self::$driver === 'pgsql'
                ? "CREATE DATABASE \"$dbname\" WITH OWNER = \"$owner\" ENCODING = 'UTF8'"
                : "CREATE DATABASE `$dbname`";

            self::$pdo->exec($sql);
        } catch (Exception $e) {
            if (!\str_contains($e->getMessage(), 'exists')) {
                throw $e;
            }
        }
    }

    /**
     * Grant all privileges to a user on a database.
     *
     * @param string $dbname The name of the database to grant privileges on.
     * @param string $username The username to grant privileges to.
     *
     * @return void
     */
    public static function grantPrivileges(string $dbname, string $username): void
    {
        $Validator = Validator::make(
            [
                'dbname'   => $dbname,
                'username' => $username,
            ],
            [
                'dbname'   => new DataBaseNameRule(),
                'username' => new UserNameRule(),
            ]
        );

        if ($Validator->fails()) {
            return;
        }

        if (self::$driver === 'sqlite') {
            return;
        }

        $sql = self::$driver === 'pgsql'
            ? "GRANT ALL PRIVILEGES ON DATABASE \"$dbname\" TO \"$username\""
            : "GRANT ALL PRIVILEGES ON `$dbname`.* TO '$username'@'%'";

        self::$pdo->exec($sql);
    }

    /**
     * Drop a tenant database.
     *
     * @param string $dbname The name of the database to drop.
     *
     * @return void
     */
    public static function dropTenantDatabase(string $dbname): void
    {
        $tenantName = self::$tenantName;

        $Validator = Validator::make(
            [
                'dbname' => $dbname,
            ],
            [
                'dbname' => new DataBaseNameRule(),
            ]
        );

        if ($Validator->fails()) {
            return;
        }

        if (self::$driver === 'sqlite') {
            $path = base_path("site/$tenantName/database.sqlite");
            if (\file_exists($path)) {
                \unlink($path);
            }

            return;
        }

        $sql = self::$driver === 'pgsql'
            ? "DROP DATABASE IF EXISTS \"$dbname\""
            : "DROP DATABASE IF EXISTS `$dbname`";

        self::$pdo->exec($sql);
    }

    /**
     * Drop a database user.
     *
     * @param string $username The username to drop.
     *
     * @return void
     */
    public static function dropUser(string $username): void
    {
        $Validator = Validator::make(
            [
                'username' => $username,
            ],
            [
                'username' => new UserNameRule(),
            ]
        );

        if ($Validator->fails()) {
            return;
        }


        if (self::$driver === 'sqlite') {
            return;
        }

        $sql = self::$driver === 'pgsql'
            ? "DROP USER IF EXISTS \"$username\""
            : "DROP USER IF EXISTS '$username'@'%'";

        self::$pdo->exec($sql);
    }

    /**
     * Creates a tenant by creating a database user, a database, and granting the user all privileges on the database.
     *
     * @param array<string, mixed> $credentials An associative array containing the credentials for the tenant, including 'DB_DATABASE', 'DB_USERNAME', and 'DB_PASSWORD'.
     * @param bool $exists Whether the tenant already exists or not. If true, the method will skip creating the user and database.
     *
     * @return void
     */
    public static function createTenant(array $credentials, bool $exists = false): void
    {
        if (!$exists && isset($credentials['DB_DATABASE']) && isset($credentials['DB_USERNAME']) && isset($credentials['DB_PASSWORD'])) {
            self::createUser($credentials['DB_USERNAME'], $credentials['DB_PASSWORD']);
            self::createDatabase($credentials['DB_DATABASE'], $credentials['DB_USERNAME']);
            self::grantPrivileges($credentials['DB_DATABASE'], $credentials['DB_USERNAME']);
        }
    }

    /**
     * Drop a tenant by removing its database and user.
     *
     * @param array<string, mixed> $credentials An associative array containing the credentials for the tenant, including 'DB_DATABASE' and 'DB_USERNAME'.
     * @param bool $exists Whether the tenant already exists or not. If true, the method will proceed to drop the user and database.
     *
     * @return void
     */
    public static function dropTenant(array $credentials, bool $exists = true): void
    {
        if ($exists && isset($credentials['DB_DATABASE'], $credentials['DB_USERNAME'])) {
            TenantDatabaseManager::dropTenantDatabase($credentials['DB_DATABASE']);
            TenantDatabaseManager::dropUser($credentials['DB_USERNAME']);
        }
    }

    /**
     * Lists all databases in the current connection.
     *
     * @return string[] A list of database names.
     */
    public static function listDatabases(): array
    {
        return match (self::$driver) {
            'pgsql'  => self::$pdo->query('SELECT datname FROM pg_database WHERE datistemplate = false')->fetchAll(PDO::FETCH_COLUMN),
            'mysql'  => self::$pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN),
            'sqlite' => [\basename(self::$pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS))], // Chemin de fichier
            default  => throw new \RuntimeException('Unsupported driver'),
        };
    }

    /**
     * Lists all users in the current connection.
     *
     * @return string[] A list of user names.
     */
    public static function listUsers(): array
    {
        return match (self::$driver) {
            'pgsql'  => self::$pdo->query('SELECT rolname FROM pg_roles')->fetchAll(PDO::FETCH_COLUMN),
            'mysql'  => self::$pdo->query('SELECT User FROM mysql.user')->fetchAll(PDO::FETCH_COLUMN),
            'sqlite' => [], // Pas d'utilisateurs SQLite
            default  => throw new \RuntimeException('Unsupported driver'),
        };
    }

    /**
     * Check if a database with the given name exists.
     *
     * @param string $name The name of the database to check.
     *
     * @return bool True if the database exists, false otherwise.
     */
    public static function databaseExists(string $name): bool
    {
        $driver = self::$driver;

        return match ($driver) {
            'pgsql'  => (bool) self::$pdo->query("SELECT 1 FROM pg_database WHERE datname = '{$name}'")->fetch(),
            'mysql'  => (bool) self::$pdo->query("SHOW DATABASES LIKE '{$name}'")->fetch(),
            'sqlite' => \file_exists(database_path("{$name}.sqlite")),
            default  => throw new \RuntimeException("Unsupported driver: {$driver}"),
        };
    }

    /**
     * Check if a user with the given name exists.
     *
     * @param string $username The name of the user to check.
     *
     * @return bool True if the user exists, false otherwise.
     */
    public static function userExists(string $username): bool
    {
        $driver = self::$driver;

        return match ($driver) {
            'pgsql'  => (bool) self::$pdo->query("SELECT 1 FROM pg_roles WHERE rolname = '{$username}'")->fetch(),
            'mysql'  => (bool) self::$pdo->query("SELECT 1 FROM mysql.user WHERE User = '{$username}'")->fetch(),
            'sqlite' => false, // Pas de gestion d'utilisateurs dans SQLite
            default  => throw new \RuntimeException("Unsupported driver: {$driver}"),
        };
    }

    /**
     * Dump the database of the given tenant to a file.
     *
     * @param string $tenantEnvPath The path to the tenant's .env file.
     * @param string $destination The path where the dump will be saved.
     * @param OutputStyle|null $console The console output (default is null).
     * @param string|null $driver The database driver to use (default is null).
     *
     * @throws Exception If there is a problem while dumping the database.
     */
    public static function dump(string $tenantEnvPath, string $destination, ?OutputStyle $console, ?string $driver = null): void
    {
        // 3. Charger l'env du tenant
        $envPath       = checkFileExists("{$tenantEnvPath}/.env");
        $masterEnvPath = checkFileExists(base_path('.env'));

        $env       = \parse_ini_file($envPath);
        $masterEnv = \parse_ini_file($masterEnvPath);

        $dbName = $env['DB_DATABASE']       ?? null; // database name of the current tenant not the for global user with necessary permissions
        $user   = $masterEnv['DB_USERNAME'] ?? null; //global user with necessaries permissions
        $pass   = $masterEnv['DB_PASSWORD'] ?? null; //global user with necessaries permissions

        if (!$dbName || !$user || !$pass) {
            throw new Exception("DB_DATABASE, DB_USERNAME ou DB_PASSWORD manquant dans l'env");
        }

        $driver = $driver ?? getDriver($masterEnv);

        // 4. Dump de la base de données
        $dumpPath = "{$destination}/{$dbName}.sql";
        if ($driver === 'pgsql') {
            $cmd = \sprintf(
                'PGPASSWORD=%s pg_dump -U %s -h 127.0.0.1 -F p %s > %s',
                safeShell($pass, ShellContextEnum::ENV()),
                safeShell($user, ShellContextEnum::ARG()),
                safeShell($dbName, ShellContextEnum::ARG()),
                safeShell($dumpPath, ShellContextEnum::FILE()),
            );
        } elseif ($driver === 'mysql') {
            $cmd = \sprintf(
                'mysqldump -u%s -p%s %s > %s',
                safeShell($user, ShellContextEnum::ENV()),
                safeShell($pass, ShellContextEnum::ENV()),
                safeShell($dbName, ShellContextEnum::ARG()),
                safeShell($dumpPath, ShellContextEnum::FILE()),
            );
        } else {
            throw new Exception("Driver non supporté : $driver");
        }

        \exec($cmd, $output, $exitCode);
        runInConsole(fn () => $console?->writeln(\implode(PHP_EOL, $output)));
        if ($exitCode !== 0) {
            runInConsole(fn () => $console?->writeln("<error>Échec du dump de la base de données pour $dbName. Vérifiez vos informations de connexion ou soit la base de donnée n'existe pas</error>"));
        }
    }

    /**
     * Import a database dump into the tenant's database.
     *
     * @param string $tenantEnvPath The path to the tenant's .env file.
     * @param string $extractPath The path where the dump was extracted.
     * @param OutputStyle|null $console The console output (default is null).
     * @param string|null $driver The database driver to use (default is null).
     *
     * @throws Exception If there is a problem while importing the database.
     */
    public static function import(string $tenantEnvPath, string $extractPath, ?OutputStyle $console, ?string $driver = null): void
    {
        // 3. Charger l'env du tenant
        $envPath       = checkFileExists("{$tenantEnvPath}");
        $masterEnvPath = checkFileExists(base_path('.env'));

        $env       = \parse_ini_file($envPath);
        $masterEnv = \parse_ini_file($masterEnvPath);

        $dbName = $env['DB_DATABASE']       ?? null; // database name of the current tenant not the for global user with necessary permissions
        $user   = $masterEnv['DB_USERNAME'] ?? null; //global user with necessaries permissions
        $pass   = $masterEnv['DB_PASSWORD'] ?? null; //global user with necessaries permissions

        if (!$dbName || !$user || !$pass) {
            runInConsole(fn () => $console?->writeln('<error>Erreur lors de l’import de la base de données </error>'));

            return;
        }

        $driver = $driver ?? getDriver($masterEnv);

        // 4. Restaurer la base de données
        $sqlFile = checkFileExists("$extractPath/{$dbName}.sql");

        // Suppression de la ligne SET transaction_timeout
        $content = \file_get_contents($sqlFile);
        $content = \preg_replace('/^SET\s+transaction_timeout.*;$/m', '', $content);
        \file_put_contents($sqlFile, $content);

        if ($driver === 'pgsql') {
            // Création de la DB (si elle n'existe pas)
            $createDbCmd = \sprintf(
                'PGPASSWORD=%s createdb -U %s -h 127.0.0.1 %s',
                safeShell($pass, ShellContextEnum::ENV()),
                safeShell($user, ShellContextEnum::ARG()),
                safeShell($dbName, ShellContextEnum::ARG()),
            );

            \exec($createDbCmd, $output, $exitCode);
            runInConsole(fn () => $console?->writeln(\implode(PHP_EOL, $output)));

            // Import
            $importCmd = \sprintf(
                'PGPASSWORD=%s psql -U %s -h 127.0.0.1 -d %s -f %s',
                safeShell($pass, ShellContextEnum::ENV()),
                safeShell($user, ShellContextEnum::ARG()),
                safeShell($dbName, ShellContextEnum::ARG()),
                safeShell($sqlFile, ShellContextEnum::FILE()),
            );
        } elseif ($driver === 'mysql') {
            $createDbCmd = \sprintf(
                'mysql -u%s -p%s -e "CREATE DATABASE IF NOT EXISTS %s"',
                safeShell($user, ShellContextEnum::ARG()),
                safeShell($pass, ShellContextEnum::ENV()),
                safeShell($dbName, ShellContextEnum::ARG()),
            );

            \exec($createDbCmd, $output, $exitCode);
            runInConsole(fn () => $console?->writeln(\implode(PHP_EOL, $output)));


            $importCmd = \sprintf(
                'mysql -u%s -p%s %s < %s',
                safeShell($user, ShellContextEnum::ARG()),
                safeShell($pass, ShellContextEnum::ENV()),
                safeShell($dbName, ShellContextEnum::ARG()),
                safeShell($sqlFile, ShellContextEnum::FILE()),
            );
        } else {
            throw new Exception("Driver non supporté : $driver");
        }

        // add role before import
        self::createUser($env['DB_USERNAME'], $env['DB_PASSWORD']);

        \exec($importCmd, $output, $exitCode);
        runInConsole(fn () => $console?->writeln(\implode(PHP_EOL, $output)));

        // grante privilleges
        self::grantPrivileges($env['DB_DATABASE'], $env['DB_USERNAME']);

        if ($exitCode !== 0) {
            runInConsole(fn () => $console?->writeln("<error>Erreur lors de l’import de la base de données : $dbName</error>"));
        } else {
            runInConsole(fn () => $console?->writeln("<info>Base de données restaurée : $dbName</info>"));
        }
    }
}
