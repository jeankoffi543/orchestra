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

    /** @var array<string, mixed> */
    public static array $defaulEnv = [];

    public static function init(string $moduleStubPath): void
    {
        self::$sitePath       = getBasePath();
        self::$stubPasth      = getStubPath();
        self::$moduleStubPath = $moduleStubPath;

        self::$defaulEnv['DB_PASSWORD']         = '';
        self::$defaulEnv['DB_CONNECTION']       = 'mysql';
        self::$defaulEnv['DB_HOST']             = '127.0.0.1';
        self::$defaulEnv['DB_PORT']             = '3306';
        self::$defaulEnv['APP_URL']             = 'http://localhost';
        self::$defaulEnv['APP_DEBUG']           = true;
        self::$defaulEnv['APP_KEY']             = '';
        self::$defaulEnv['APP_ENV']             = 'local';
        self::$defaulEnv['APP_LOCALE']          = 'fr';
        self::$defaulEnv['APP_FALLBACK_LOCALE'] = 'fr';
        self::$defaulEnv['APP_FAKER_LOCALE']    = 'fr_FR';
    }

    /**
     * Create a new tenant with the given data.
     *
     * This function validates the provided tenant data, creates a new tenant directory with a stub site,
     * generates database credentials, and optionally migrates the tenant's database schema.
     * It also links the tenant storage and adds the tenant's domain to the tenants file.
     *
     * @param array<string, mixed> $data An associative array containing tenant data, including 'name' and 'domains'.
     * @param string|null $driver The database driver to be used, default is 'pgsql'.
     * @param bool $migrate Whether to migrate the tenant's database schema, default is true.
     *
     * @throws \Exception If the tenant already exists or if there is an error during the creation process.
     * @return void
     */
    public static function createTenant(array $data, ?string $driver = 'pgsql', bool $migrate = true): void
    {
        $rollback = new RollbackManager();

        try {
            // Validate data
            self::validateData($data, [
                'name'    => ['required', new TenantNameRule()],
                'domains' => ['required', new DomainRule()],
            ]);

            $name     = parseTenantName($data['name']);
            $domain   = $data['domains'];
            $basePath = getBasePath($name);

            // stub
            $stubSite = module_path('.stub/.site');

            // check if tenant already exists
            if (File::exists($basePath)) {
                throw new \Exception('Tenant already exists', Response::HTTP_CONFLICT);
            }

            LessTenancy::createStub($stubSite, $basePath, $rollback);

            // Generate database credentials
            $credentials                        = generateDatabaseCredentials($name);
            $credentials['DB_PASSWORD']         = '"' . generatePassword(12) . '"';
            $credentials['DB_CONNECTION']       = $driver;
            $credentials['DB_HOST']             = '127.0.0.1';
            $credentials['DB_PORT']             = '5432';
            $credentials['APP_URL']             = request()->getScheme() . '://' . $domain;
            $credentials['APP_DEBUG']           = false;
            $credentials['APP_KEY']             = 'base64:' . \base64_encode(\random_bytes(32));
            $credentials['APP_ENV']             = 'production';
            $credentials['APP_LOCALE']          = 'fr';
            $credentials['APP_FALLBACK_LOCALE'] = 'fr';
            $credentials['APP_FAKER_LOCALE']    = 'fr_FR';

            LessTenancy::addTeantCreatingEnv($credentials, $basePath, $rollback);


            if ($migrate) {
                // Migrate database
                self::migrate($credentials, $name, $rollback, self::checkIfCredentialsExist($credentials));
            }

            // Link tenant storage
            LessTenancy::linkTenant($name, $rollback);

            // add $name=$domain to tenants file
            LessTenancy::addDomain($name, $domain, $rollback);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete an existing tenant.
     *
     * This function removes the specified tenant by unlinking its storage,
     * archiving its site directory, and optionally dropping its database.
     * It also removes the tenant's domain from the tenants file.
     *
     * @param string $name The name of the tenant to delete.
     * @param string|null $driver The database driver to use, default is 'pgsql'.
     * @param string|null $domain The domain of the tenant to remove from the tenants file.
     *
     * @throws \Exception If an error occurs during the deletion process.
     * @return void
     */
    public static function deleteTenant(string $name, ?string $driver = 'pgsql', ?string $domain = ''): void
    {
        $rollback = new RollbackManager();

        try {
            $name     = parseTenantName($name);
            $basePath = getBasePath($name);

            // Generate database credentials
            $credentials = parseEnvPath($name);

            // unlink tenant storage
            LessTenancy::unlinkTenant($name, $rollback);

            // check if tenant already exists
            if (File::exists($basePath)) {
                // File::deleteDirectory($basePath);
                TenantArchiver::archiveTenant($name, $rollback, $driver);
            };

            LessTenancy::dropTenantDatabase($credentials, $rollback, self::checkIfCredentialsExist($credentials));

            // remove $name=$domain from tenants file
            LessTenancy::removeDomain($name, $domain, $rollback);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update an existing tenant.
     *
     * This function updates the specified tenant by renaming its directory,
     * updating its environment variables, and optionally updating its domain
     * in the tenants file.
     *
     * @param array<string, mixed> $data An associative array containing the updated tenant data,
     *                                   including the 'name' of the tenant and any other fields to update.
     *
     * @throws \Exception If an error occurs during the update process.
     * @return void
     */
    public static function updateTenant(array $data): void
    {
        $rollback = new RollbackManager();
        try {
            $name     = parseTenantName($data['name']);
            $basePath = getBasePath($name);
            if (File::exists($basePath)) {
                $newTenantName = parseTenantName($data['by']);
                $domain        = null;

                $request = request();
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
                LessTenancy::renameTenant($basePath, getBasePath($newTenantName), $rollback);

                LessTenancy::unlinkTenant($name, $rollback);
                LessTenancy::linkTenant($newTenantName, $rollback);
            } else {
                throw new \Exception('Tenant not found', Response::HTTP_NOT_FOUND);
            }

            // remove $name=$domain from tenants file
            LessTenancy::updateDomain($name, $newTenantName, $domain, $rollback);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restores a tenant that was previously backed up and archived.
     *
     * This function restores a tenant by restoring its database and files.
     * It also adds the tenant's domain to the list of domains.
     *
     * @param string $tenantName The name of the tenant to restore.
     * @param string $driver The database driver to use for the tenant's database.
     * @param OutputStyle $console An optional console object to use for output.
     * @return void
     */
    public static function restore(string $tenantName, string $driver = 'pgsql', ?OutputStyle $console = null): void
    {
        $rollback   = new RollbackManager();
        $tenantName = parseTenantName($tenantName);
        TenantArchiver::restoreTenant($tenantName, $rollback, $driver, $console);
        $domain = \parse_ini_file(getEnvPath($tenantName))['APP_DOMAIN'];
        self::addDomain($tenantName, $domain);
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
            collect($content)
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
     * @param array<string, mixed> $credentials The database credentials with
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

    /**
     * Retrieve the value of a specified environment variable from a file.
     *
     * This function looks for the given key in the environment file located at the specified path.
     * If the key exists, its value is returned; otherwise, null is returned.
     *
     * @param string $key The name of the environment variable to retrieve.
     * @param string|null $path The path to the environment file. If null, a default path is used.
     *
     * @return string|null The value of the environment variable, or null if the key does not exist.
     */
    public static function getEnvValue(string $key, ?string $path = null): ?string
    {
        return isset(self::convertEnvFileToArray($path)[$key]) ? self::convertEnvFileToArray($path)[$key] : null;
    }

    /**
     * Set the value of a specified environment variable in a file.
     *
     * This function updates the environment file located at the specified path
     * by setting the value of the given key to the given value. If the key
     * does not exist in the file, it is added; otherwise, its value is updated.
     *
     * @param string $key The name of the environment variable to set.
     * @param string $value The value to set for the environment variable.
     * @param string|null $path The path to the environment file. If null, a default path is used.
     */
    public static function setEnvValue(string $key, string $value, ?string $path = null): void
    {
        $env       = self::convertEnvFileToArray($path);
        $env[$key] = $value;
        $env       = collect($env)->map(function ($item, $key) {
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
        return collect($env)->mapWithKeys(function ($item) {
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

    /**
     * Remove a domain associated with a tenant.
     *
     * This function removes the domain entry corresponding to the given tenant
     * name from the tenants configuration file. After removing the domain,
     * the updated list of tenants is saved back to the file.
     *
     * @param string $name The name of the tenant whose domain is to be removed.
     */
    public static function removeDomain(string $name): void
    {
        $tenantsFile = getBasePath() . '/.tenants';
        $tenants     = \parse_ini_file($tenantsFile);

        foreach ($tenants as $key => $value) {
            if ($key === $name) {
                unset($tenants[$key]);
            }
        }

        self::parseDomainContent($tenants, $tenantsFile);
    }

    /**
     * Adds a domain associated with a tenant.
     *
     * This function adds the domain entry corresponding to the given tenant
     * name to the tenants configuration file. If the domain already exists,
     * it is not added again.
     *
     * @param string $name The name of the tenant whose domain is to be added.
     * @param string $domain The domain to add to the tenant.
     */
    public static function addDomain(string $name, string $domain): void
    {
        if (!self::getDomain($name)) {
            $tenantsFile = getBasePath() . '/.tenants';
            File::append($tenantsFile, "$name=$domain\n");
        }
    }

    /**
     * Return the domain associated with a tenant.
     *
     * This function returns the domain associated with the given tenant
     * name. If the tenant does not exist, it returns null.
     *
     * @param string $name The name of the tenant whose domain is to be retrieved.
     * @return string|null The domain associated with the tenant, or null if the tenant does not exist.
     */
    public static function getDomain(string $name): ?string
    {
        $tenantsFile = getBasePath() . '/.tenants';
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
        $tenantsFile = getBasePath() . '/.tenants';
        $tenants     = \parse_ini_file($tenantsFile);

        return $tenants;
    }

    /**
     * Updates the domain associated with a tenant.
     *
     * This function updates the domain associated with the given tenant
     * name. If the domain already exists, it is not added again.
     * If the value of $domain is not null, it replaces the old
     * domain with the new one.
     *
     * @param string $name The name of the tenant whose domain is to be updated.
     * @param string $value The new name of the tenant.
     * @param string|null $domain The new domain to associate with the tenant, or null to leave the domain unchanged.
     */
    public static function updateDomain(string $name, string $value, ?string $domain = null): void
    {
        $tenantsFile = getBasePath() . '/.tenants';
        $tenants     = \parse_ini_file($tenantsFile);
        if ($domain) {
            $tenants[$name] = $domain;
        }
        // change name key by value
        $tenants[$value] = $tenants[$name];
        unset($tenants[$name]);
        self::parseDomainContent($tenants, $tenantsFile);
    }

    /**
     * Return the name of the current tenant based on the current request domain.
     *
     * @return string|null The name of the current tenant, or null if no tenant is found with the current request domain.
     */
    public static function getCurrent(): ?string
    {
        $request = request();
        $domain  = $request->getHost();
        $tenants = self::getTenants();
        $name    = collect($tenants)->filter(fn ($t) => $t === $domain)->toArray();

        return \array_key_first($name);
    }

    /**
     * Switches to the current tenant based on the current request domain.
     *
     * This function determines the current tenant using the request's domain
     * and switches the application context to that tenant.
     */
    public static function switchToCurrent(): void
    {
        self::switchToTenant(self::getCurrent());
    }

    /**
     * Runs the given callback in the context of the current tenant.
     *
     * This function determines the current tenant using the request's domain
     * and runs the given callback in the context of that tenant.
     *
     * @param Closure $callback The callback to run in the context of the current tenant.
     */
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
    public static function migrate(array $credentials, string $name, RollbackManager $rollback, bool $exists = true): void
    {
        // Create tenant database and user
        LessTenancy::createTenantDatabase($credentials, $rollback, $exists);

        // Migrate tenant database
        Artisan::call("orchestra:migrate $name");
    }

    /**
     * Switch to a tenant
     *
     * @param string|null $name The tenant name
     *
     * @throws \Exception
     */
    public static function switchToTenant(?string $name = null): void
    {
        $tenantManager = new TenantManager();
        try {
            $name = parseTenantName($name);

            $tenantManager->switchTo($name);
        } catch (\Exception $e) {
            if ($tenantManager instanceof TenantManager) {
                $tenantManager->rebase($name);
            }
            throw new \Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Run the given callback in the context of the given tenant.
     *
     * This function determines the given tenant using the given name
     * and runs the given callback in the context of that tenant.
     *
     * @param string $name The tenant name
     * @param Closure $callback The callback to run in the context of the given tenant
     * @return void
     *
     * @throws \Exception If the tenant is not found or if there is a problem while running the callback.
     */
    public static function useTenant(string $name, Closure $callback): void
    {
        $tenantManager = new TenantManager();
        try {
            $name = parseTenantName($name);
            $tenantManager->runFor($name, $callback);
            $tenantManager->rebase($name);
        } catch (\Exception $e) {
            if ($tenantManager instanceof TenantManager) {
                $tenantManager->rebase($name);
            }
            throw new \Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reload the domains from the .tenants file and the site directory.
     *
     * This function reads the .tenants file and the directories in the site
     * directory, and updates the domains in the database. If a directory does
     * not have a corresponding domain in the .tenants file, it will be added
     * to the .tenants file.
     *
     * @return void
     */
    public static function reloadDomain(): void
    {
        // TODO
        // \dd('dd');
        $tenantsFile = getBasePath() . '/.tenants';
        $tenants     = \parse_ini_file($tenantsFile);
        // get directories name from site directory
        $directories = \array_diff(\scandir(getBasePath()), ['.', '..', '.gitignore', '.tenants']);

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
        $directoryIterator = self::directoryIterator(getBasePath());
        $tenants           = [];
        foreach ($directoryIterator as $directory) {
            if (self::getDomain($fileName = $directory->getFilename())) {
                $tenants[] = $fileName;
            }
        }

        return $tenants;
    }
}
