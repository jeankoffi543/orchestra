<?php

namespace Kjos\Orchestra\Facades\Concerns;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Kjos\Orchestra\Services\TenantDatabaseManager;

class LessTenancy
{
    /**
     * Create a stub directory by copying contents from a source directory.
     *
     * This function copies all contents from the specified source directory
     * to the destination directory, including an environment configuration
     * file. It also adds a rollback action to delete the destination
     * directory if needed.
     *
     * @param string $from The source directory path.
     * @param string $to The destination directory path.
     * @param RollbackManager $rollback The rollback manager to handle
     *                                  rollback actions.
     *
     * @return void
     */
    public static function createStub(string $from, string $to, RollbackManager $rollback): void
    {
        rollback_catch(
            function () use ($from, $to, $rollback) {
                File::copyDirectory($from, "$to/");
                File::copy("$from/.env.example", "$to/.env");
                $rollback->add(fn () => File::deleteDirectory($to));
            },
            $rollback
        );
    }

    /**
     * Create a tenant database using the provided credentials.
     *
     * This function attempts to create a new tenant database using the given
     * credentials. If the database creation is successful, a rollback action
     * is added to drop the database in case of future errors or rollbacks.
     *
     * @param array<string, mixed> $credentials An associative array containing the database
     *                           credentials required to create the tenant database.
     * @param RollbackManager $rollback The rollback manager to handle rollback actions
     *                                  in case of errors during the database creation.
     * @param bool $exists A flag indicating whether the database already exists.
     *                     Defaults to true.
     *
     * @return void
     *
     * @throws \Exception If an error occurs during the database creation process.
     */
    public static function createTenantDatabase(array $credentials, RollbackManager $rollback, bool $exists = true): void
    {
        rollback_catch(
            function () use ($credentials, $rollback, $exists) {
                TenantDatabaseManager::createTenant($credentials, $exists);

                $rollback->add(function () use ($credentials, $exists) {
                    TenantDatabaseManager::dropTenant($credentials, $exists);
                });
            },
            $rollback
        );
    }

    /**
     * Drop a tenant database using the provided credentials.
     *
     * This function attempts to drop a tenant database using the given
     * credentials. If the database drop is successful, a rollback action
     * is added to recreate the database in case of future errors or rollbacks.
     *
     * @param array<string, mixed> $credentials An associative array containing the database
     *                           credentials required to drop the tenant database.
     * @param RollbackManager $rollback The rollback manager to handle rollback actions
     *                                  in case of errors during the database drop.
     * @param bool $exists A flag indicating whether the database already exists.
     *                     Defaults to true.
     *
     * @return void
     *
     * @throws \Exception If an error occurs during the database drop process.
     */
    public static function dropTenantDatabase(array $credentials, RollbackManager $rollback, bool $exists = true): void
    {
        rollback_catch(
            function () use ($credentials, $rollback, $exists) {
                TenantDatabaseManager::dropTenant($credentials, $exists);
                $rollback->add(function () use ($credentials, $exists) {
                    TenantDatabaseManager::createTenant($credentials, $exists);
                });
            },
            $rollback
        );
    }

    /**
     * Rename a tenant directory and add a rollback action.
     *
     * This function renames a tenant directory from the specified source path
     * to the target path. If an error occurs during the process, a rollback
     * action is added to reverse the renaming operation.
     *
     * @param string $from The original path of the tenant directory.
     * @param string $to The new path for the tenant directory.
     * @param RollbackManager $rollback The rollback manager to handle rollback actions
     *                                  in case of errors during the rename operation.
     *
     * @return void
     *
     * @throws \Exception If an error occurs during the rename operation.
     */
    public static function renameTenant(string $from, string $to, RollbackManager $rollback): void
    {
        rollback_catch(
            function () use ($from, $to, $rollback) {
                File::move($from, $to);
                $rollback->add(fn () => File::move($to, $from));
            },
            $rollback
        );
    }

    /**
     * Update a tenant's domain and add a rollback action.
     *
     * This function updates a tenant's domain name from the specified original
     * name to the new name. If an error occurs during the process, a rollback
     * action is added to reverse the update operation.
     *
     * @param string $name The original name of the tenant.
     * @param string $newName The new name of the tenant.
     * @param string $domain The domain of the tenant.
     * @param RollbackManager $rollback The rollback manager to handle rollback actions
     *                                  in case of errors during the update operation.
     *
     * @return void
     *
     * @throws \Exception If an error occurs during the update operation.
     */
    public static function updateDomain(string $name, string $newName, string $domain, RollbackManager $rollback): void
    {
        rollback_catch(
            function () use ($name, $newName, $rollback, $domain) {
                Tenancy::updateDomain($name, $newName, $domain);

                $rollback->add(fn () => Tenancy::updateDomain($newName, $name, $domain));
            },
            $rollback
        );
    }

    /**
     * Links a tenant by executing the appropriate Artisan command.
     *
     * This function attempts to link a tenant using the provided tenant name.
     * It uses a rollback action to unlink the tenant in case of errors during
     * the linking process.
     *
     * @param string $name The name of the tenant to link.
     * @param RollbackManager $rollback The rollback manager to handle rollback actions
     *                                  in case of errors during the linking process.
     *
     * @return void
     *
     * @throws \Exception If an error occurs during the linking process.
     */
    public static function linkTenant(string $name, RollbackManager $rollback): void
    {
        rollback_catch(
            function () use ($name, $rollback) {
                Artisan::call("orchestra:link $name --force");
                $rollback->add(fn () => Artisan::call("orchestra:unlink $name"));
            },
            $rollback
        );
    }

    /**
     * Unlinks a tenant by executing the appropriate Artisan command.
     *
     * This function attempts to unlink a tenant using the provided tenant name.
     * It uses a rollback action to link the tenant in case of errors during the
     * unlinking process.
     *
     * @param string $name The name of the tenant to unlink.
     * @param RollbackManager $rollback The rollback manager to handle rollback actions
     *                                  in case of errors during the unlinking process.
     *
     * @return void
     *
     * @throws \Exception If an error occurs during the unlinking process.
     */
    public static function unlinkTenant(string $name, RollbackManager $rollback): void
    {
        rollback_catch(
            function () use ($name, $rollback) {
                Artisan::call("orchestra:unlink $name");
                $rollback->add(fn () => Artisan::call("orchestra:link $name --force"));
            },
            $rollback
        );
    }

    /**
     * Sets environment variables for a tenant during its creation and adds a rollback action.
     *
     * This function sets the specified environment variables for a tenant during its creation.
     * If an error occurs during the process, a rollback action is added to set the default
     * environment variables for the tenant.
     *
     * @param array<string, mixed> $envs The environment variables to set for the tenant.
     * @param string $path The path to the tenant directory.
     * @param RollbackManager $rollback The rollback manager to handle rollback actions
     *                                  in case of errors during the setting of environment variables.
     *
     * @return void
     *
     * @throws \Exception If an error occurs during the setting of environment variables.
     */
    public static function addTeantCreatingEnv(array $envs, string $path, RollbackManager $rollback): void
    {
        rollback_catch(
            function () use ($envs, $path, $rollback) {
                Tenancy::setTenantEnv($envs, $path);
                $rollback->add(function () use ($path) {
                    Tenancy::setTenantEnv(Tenancy::$defaulEnv, $path);
                });
            },
            $rollback
        );
    }

    /**
     * Add a domain to a tenant and set up a rollback action.
     *
     * This function associates a domain with a tenant by adding the domain
     * to the tenant's configuration. If an error occurs during the process,
     * a rollback action is added to remove the domain from the tenant.
     *
     * @param string $name The name of the tenant.
     * @param string $domain The domain to associate with the tenant.
     * @param RollbackManager $rollback The rollback manager to handle rollback actions
     *                                  in case of errors during the domain addition.
     *
     * @return void
     *
     * @throws \Exception If an error occurs during the domain addition.
     */
    public static function addDomain(string $name, string $domain, RollbackManager $rollback): void
    {
        rollback_catch(
            function () use ($name, $domain, $rollback) {
                Tenancy::addDomain($name, $domain);
                $rollback->add(function () use ($name) {
                    Tenancy::removeDomain($name);
                });
            },
            $rollback
        );
    }

    /**
     * Removes a domain from a tenant and sets up a rollback action.
     *
     * This function removes a domain from a tenant by removing the domain
     * from the tenant's configuration. If an error occurs during the process,
     * a rollback action is added to associate the domain with the tenant.
     *
     * @param string $name The name of the tenant.
     * @param string $domain The domain to remove from the tenant.
     * @param RollbackManager $rollback The rollback manager to handle rollback actions
     *                                  in case of errors during the domain removal.
     *
     * @return void
     *
     * @throws \Exception If an error occurs during the domain removal.
     */
    public static function removeDomain(string $name, string $domain, RollbackManager $rollback): void
    {
        rollback_catch(
            function () use ($name, $domain, $rollback) {
                Tenancy::removeDomain($name);
                $rollback->add(function () use ($name, $domain) {
                    Tenancy::addDomain($name, $domain);
                });
            },
            $rollback
        );
    }
}
