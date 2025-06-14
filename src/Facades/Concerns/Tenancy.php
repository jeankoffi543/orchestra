<?php

namespace Kjos\Orchestra\Facades\Concerns;

use Closure;
use Illuminate\Console\OutputStyle;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Kjos\Orchestra\Contexts\TenantManager;
use Kjos\Orchestra\Rules\DomainRule;
use Kjos\Orchestra\Rules\TenantNameRule;
use Kjos\Orchestra\Services\TenantDatabaseManager;
use RecursiveIteratorIterator;

class Tenancy
{
    protected static string $moduleStubPath;
    protected static string $stubPasth;
    protected static string $sitePath;
    protected static string $composerPath;
    protected static string $moduleSitePath;

    public static function init(string $moduleStubPath): void
    {
        self::$sitePath       = \getBasePath();
        self::$stubPasth      = \getStubPath();
        self::$moduleStubPath = $moduleStubPath;
    }

    /**
     * Create a new tenant with the given data
     *
     * @param array<string, mixed> $data
     * @param string $driver
     * @param boolean $migrate
     *
     * @throws \Exception
     *
     * @return void
     */
    public static function createTenant(array $data, ?string $driver = 'pgsql', bool $migrate = true): void
    {
        $basePath       = null;
        $credentials    = null;
        $name           = null;
        $moduleStubPath = self::$moduleStubPath;

        try {
            if (!isset($data['name'], $data['domains'])) {
                throw new \Exception('name or domain is required', Response::HTTP_BAD_REQUEST);
            }

            // Validate data
            self::validateData($data, [
                'name'    => ['required', new TenantNameRule()],
                'domains' => ['required', new DomainRule()],
            ]);

            $name     = \parseTenantName($data['name']);
            $domain   = $data['domains'];
            $basePath = \getBasePath($name);

            // stub
            $stubSite = "$moduleStubPath/.site";

            // check if tenant already exists
            if (File::exists($basePath)) {
                throw new \Exception('Tenant already exists', Response::HTTP_CONFLICT);
            }

            // copy stub
            File::copyDirectory($stubSite, "$basePath/");
            if (!File::exists("$basePath/.env.example")) {
                throw new \Exception('env.example not found', Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            File::copy("$basePath/.env.example", "$basePath/.env");

            // Generate database credentials
            $credentials                = \generateDatabaseCredentials($name);
            $credentials['DB_PASSWORD'] = \generatePassword(12);

            $request = \request();
            $scheme  = $request->getScheme();

            // set env content line by line
            self::setTenantEnv(
                [
                    'DB_CONNECTION'       => 'pgsql',
                    'DB_HOST'             => '127.0.0.1',
                    'DB_PORT'             => '5432',
                    'DB_DATABASE'         => $credentials['DB_DATABASE'],
                    'DB_USERNAME'         => $credentials['DB_USERNAME'],
                    'DB_PASSWORD'         => '"' . $credentials['DB_PASSWORD'] . '"',
                    'APP_URL'             => "$scheme://$domain",
                    'APP_NAME'            => $name,
                    'APP_DOMAIN'          => $domain,
                    'APP_DEBUG'           => 'false',
                    'APP_KEY'             => 'base64:' . \base64_encode(\random_bytes(32)),
                    'APP_ENV'             => 'production',
                    'APP_LOCALE'          => 'fr',
                    'APP_FALLBACK_LOCALE' => 'fr',
                    'APP_FAKER_LOCALE'    => 'fr_FR',
                ],
                $basePath
            );

            if ($migrate) {
                // Migrate database
                self::migrate($credentials, $name);
            }

            // Link tenant storage
            Artisan::call("orchestra:link $name --force");

            // add $name=$domain to tenants file
            self::addDomain($name, $domain);
        } catch (\Exception $e) {
            // If tenant already exists do nothing
            Tenancy::rollback($name, $driver);
            throw new \Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public static function rollback(string $tenantName, string $driver = 'pgsql', ?bool $deleteTenant = true): void
    {
        // remove the tenant
        $deleteTenant && self::deleteTenant($tenantName, $driver, true);

        // remove the archive
        TenantArchiver::removeZip($tenantName);
    }

    public static function deleteTenant(string $name, ?string $driver = 'pgsql', ?bool $rollback = false): void
    {
        try {
            $name     = \parseTenantName($name);
            $basePath = \getBasePath($name);

            // Generate database credentials
            $credentials = \parseEnvPath($name);

            // unlink tenant storage
            Artisan::call("orchestra:unlink $name");

            // check if tenant already exists
            if (File::exists($basePath)) {
                // File::deleteDirectory($basePath);
                TenantArchiver::archiveTenant($name, $driver);
            };

            if (
                self::checkIfCredentialsExist($credentials) && isset($credentials['DB_DATABASE'], $credentials['DB_USERNAME']) && !$rollback
            ) {
                TenantDatabaseManager::dropTenantDatabase($credentials['DB_DATABASE']);
                TenantDatabaseManager::dropUser($credentials['DB_USERNAME']);
            }

            // remove $name=$domain from tenants file
            self::removeDomain($name);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Write the given array of key-value pairs as a .tenants file.
     *
     * @param array<string, mixed> $content
     * @param string $path
     */
    public static function parseDomainContent(array $content, $path): void
    {
        $content = '# Registered tenants' . PHP_EOL . PHP_EOL .
            \collect($content)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode(PHP_EOL)
            . PHP_EOL;

        File::put($path, $content);
    }

    /**
     * Check if the provided database credentials exist.
     *
     * This function verifies the existence of the database and user specified
     * in the credentials array. It returns true if both the database and
     * user exist, otherwise it returns false.
     *
     * @param array<string, string> $credentials The database credentials with
     *                                           'DB_DATABASE' and 'DB_USERNAME' keys.
     *
     * @return bool True if both the user and database exist, otherwise false.
     */
    public static function checkIfCredentialsExist(array $credentials): bool
    {
        if (isset($credentials['DB_DATABASE']) && isset($credentials['DB_USERNAME'])) {
            if (TenantDatabaseManager::userExists($credentials['DB_USERNAME']) && TenantDatabaseManager::databaseExists($credentials['DB_DATABASE'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $path
     * @return \RecursiveIteratorIterator<\RecursiveDirectoryIterator>
     */
    public static function fileIterator(string $path): RecursiveIteratorIterator
    {
        return new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
    }

    /**
     * @param string $path
     * @return \Generator<\SplFileInfo>
     */
    public static function directoryIterator(string $path): \Generator
    {
        $iterator = new \DirectoryIterator($path);

        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isDir() && ! $fileinfo->isDot()) {
                yield $fileinfo;
            }
        }
    }

    /**
     * Validate the given data according to the given rules.
     *
     * @param array<string, mixed> $data The data to validate.
     * @param array<string, mixed> $rules The validation rules.
     *
     * @throws \Exception If the validation fails.
     */
    public static function validateData(array $data, array $rules): void
    {
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Set multiple environment variables for a tenant.
     *
     * @param array<string, string> $data An associative array of environment variables to set, where the keys are the variable names and the values are the variable values.
     * @param string $basePath The base path to the tenant's environment file.
     *
     * @throws \Exception If there is an error setting the environment variables.
     */
    public static function setTenantEnv(array $data, $basePath): void
    {
        try {
            self::setMultipleEnvValues($data, $basePath);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public static function getEnvValue(string $key, ?string $path = null): ?string
    {
        return isset(self::convertEnvFileToArray($path)[$key]) ? self::convertEnvFileToArray($path)[$key] : null;
    }

    public static function setEnvValue(string $key, string $value, ?string $path = null): void
    {
        $env       = self::convertEnvFileToArray($path);
        $env[$key] = $value;
        $env       = \collect($env)->map(function ($item, $key) {
            return "$key=$item";
        })->toArray();
        $envContent = \implode(PHP_EOL, \array_values($env)) . PHP_EOL;

        File::put("$path/.env", $envContent);
    }

    /**
     * Set multiple environment variables for a tenant.
     *
     * @param array<string, string> $envs An associative array of environment variables to set, where the keys are the variable names and the values are the variable values.
     * @param string $path The path to the environment file to update.
     *
     * @throws \Exception If there is an error setting the environment variables.
     */
    public static function setMultipleEnvValues(array $envs, ?string $path = null): void
    {
        foreach ($envs as $key => $value) {
            self::setEnvValue($key, $value, $path);
        }
    }

    /**
     * Converts the content of an environment file into an associative array.
     *
     * @param string $path The path to the environment file.
     *
     * @return array<string, string> An associative array where the keys are environment variable names and the values are their corresponding values.
     */
    public static function convertEnvFileToArray(string $path): array
    {
        $env = \preg_split('/\r\n|\r|\n/', self::readEnvFileContent($path));

        return self::_convertEnvFileToArray($env);
    }

    public static function readEnvFileContent(string $path): string
    {
        return File::get("$path/.env");
    }

    /**
     * Converts an environment string into an associative array.
     *
     * This function processes each line of the environment string,
     * ignoring lines that start with a '#' character. Each valid line
     * is expected to contain a key-value pair separated by an '=' character.
     * The resulting array contains these key-value pairs, where the keys
     * are the environment variable names and the values are their corresponding
     * values.
     *
     * @param array<mixed> $env The environment string to convert.
     *
     * @return array<string, string> An associative array of environment variables.
     */
    public static function _convertEnvFileToArray(array $env): array
    {
        return \collect($env)->mapWithKeys(function ($item) {
            if (\strpos($item, '#') === 0) {
                return [];
            }
            $_item = \explode('=', $item);
            if (\count($_item) < 2) {
                return [];
            }

            return [$_item[0] => $_item[1]];
        })->toArray();
    }

    public static function removeDomain(string $name): void
    {
        $tenantsFile = \getBasePath() . '/.tenants';
        $tenants     = \parse_ini_file($tenantsFile);

        foreach ($tenants as $key => $value) {
            if ($key === $name) {
                unset($tenants[$key]);
            }
        }

        self::parseDomainContent($tenants, $tenantsFile);
    }

    public static function addDomain(string $name, string $domain): void
    {
        if (!self::getDomain($name)) {
            $tenantsFile = \getBasePath() . '/.tenants';
            File::append($tenantsFile, "$name=$domain\n");
        }
    }

    public static function getDomain(string $name): ?string
    {
        $tenantsFile = \getBasePath() . '/.tenants';
        $tenants     = \parse_ini_file($tenantsFile);

        return $tenants[$name] ?? null;
    }

    /**
     * Return the list of tenants.
     *
     * @return array<string, string> tenant name as key and tenant data as value
     */
    public static function getTenants(): array
    {
        $tenantsFile = \getBasePath() . '/.tenants';
        $tenants     = \parse_ini_file($tenantsFile);

        return $tenants;
    }

    public static function updateDomain(string $name, string $value, ?string $domain = null): void
    {
        $tenantsFile = \getBasePath() . '/.tenants';
        $tenants     = \parse_ini_file($tenantsFile);
        if ($domain) {
            $tenants[$name] = $domain;
        }
        // change name key by value
        $tenants[$value] = $tenants[$name];
        unset($tenants[$name]);
        self::parseDomainContent($tenants, $tenantsFile);
    }

    public static function getCurrent(): ?string
    {
        $request = \request();
        $domain  = $request->getHost();
        $tenants = self::getTenants();
        $name    = \collect($tenants)->filter(fn ($t) => $t === $domain)->toArray();

        return \array_key_first($name);
    }

    public static function switchToCurrent(): void
    {
        self::switchToTenant(self::getCurrent());
    }

    public static function useCurrent(Closure $callback): void
    {
        self::useTenant(self::getCurrent(), $callback);
    }

    /**
     * Run the database migrations for the given tenant.
     *
     * @param array<string, mixed> $credentials The database credentials for the tenant.
     * @param string $name The name of the tenant.
     * @return void
     */
    public static function migrate(array $credentials, string $name): void
    {
        // Create tenant database and user
        TenantDatabaseManager::createTenant($credentials['DB_DATABASE'], $credentials['DB_USERNAME'], $credentials['DB_PASSWORD']);

        // Migrate tenant database
        Artisan::call("orchestra:migrate $name");
    }

    public static function restore(string $tenantName, string $driver = 'pgsql', ?OutputStyle $console = null): void
    {
        $tenantName = \parseTenantName($tenantName);
        TenantArchiver::restoreTenant($tenantName, $driver, $console);
        $domain = \parse_ini_file(\getEnvPath($tenantName))['APP_DOMAIN'];
        self::addDomain($tenantName, $domain);
    }

    /**
     * Update a tenant by changing its name and/or domain.
     *
     * @param array<string, mixed> $data The data to update the tenant with.
     * @param string $driver The database driver to use.
     * @return void
     *
     * @throws \Exception If the tenant is not found or if there is a problem while updating it.
     */
    public static function updateTenant(array $data, ?string $driver = 'pgsql'): void
    {
        try {
            $name     = \parseTenantName($data['name']);
            $basePath = \getBasePath($name);
            if (File::exists($basePath)) {
                $newTenantName = \parseTenantName($data['by']);
                $domain        = null;

                $request = \request();
                $scheme  = $request->getScheme();

                // Update tenant env
                $env = [];
                if (isset($data['domain'])) {
                    $domain            = $data['domain'];
                    $env['APP_URL']    = "$scheme://$domain";
                    $env['APP_DOMAIN'] = $domain;
                }

                $env['APP_NAME'] = $newTenantName;

                self::setTenantEnv(
                    $env,
                    $basePath
                );


                // Rename tenant directory
                File::move($basePath, \getBasePath($newTenantName));
                Artisan::call("orchestra:unlink $name");
                Artisan::call("orchestra:link $newTenantName --force");
            } else {
                throw new \Exception('Tenant not found', Response::HTTP_NOT_FOUND);
            }

            // remove $name=$domain from tenants file
            self::updateDomain($name, $newTenantName, $domain);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public static function switchToTenant(?string $name = null): void
    {
        $tenantManager = new TenantManager();
        try {
            $name = \parseTenantName($name);

            $tenantManager->switchTo($name);
        } catch (\Exception $e) {
            if ($tenantManager instanceof TenantManager) {
                $tenantManager->rebase($name);
            }
            throw new \Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public static function useTenant(string $name, Closure $callback): void
    {
        $tenantManager = new TenantManager();
        try {
            $name = \parseTenantName($name);
            $tenantManager->runFor($name, $callback);
            $tenantManager->rebase($name);
        } catch (\Exception $e) {
            if ($tenantManager instanceof TenantManager) {
                $tenantManager->rebase($name);
            }
            throw new \Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public static function reloadDomain(): void
    {
        // TODO
        // \dd('dd');
        $tenantsFile = \getBasePath() . '/.tenants';
        $tenants     = \parse_ini_file($tenantsFile);
        // get directories name from site directory
        $directories = \array_diff(\scandir(\getBasePath()), ['.', '..', '.gitignore', '.tenants']);

        foreach ($directories as $directory) {
            if (!isset($tenants[$directory])) {
                self::addDomain($directory, $tenants[$directory]);
            }
        }
    }

    /**
     * Get the list of tenants.
     *
     * @return array<string> The list of tenant names.
     */
    public static function listTenants(): array
    {
        $directoryIterator = self::directoryIterator(\getBasePath());
        $tenants           = [];
        foreach ($directoryIterator as $directory) {
            if (self::getDomain($fileName = $directory->getFilename())) {
                $tenants[] = $fileName;
            }
        }

        return $tenants;
    }
}
