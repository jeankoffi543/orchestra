<?php

namespace Kjos\Orchestra\Facades\Concerns;

use DOMDocument;
use Exception;
use Illuminate\Console\OutputStyle;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Kjos\Orchestra\Facades\OperaBuilder;
use Kjos\Orchestra\Rules\DomainRule;
use Kjos\Orchestra\Rules\TenantNameRule;

class Installer extends OperaBuilder
{
    private Collection $pintPaths;

    /**
     * Constructor.
     *
     * Initializes the $pintPaths property to an empty Collection.
     */
    public function __construct()
    {
        parent::__construct();
        $this->pintPaths = new Collection();
    }

    /**
     * Prepares the installation of the given master tenant.
     *
     * This function validates the provided tenant data, creates a new master tenant directory with a stub site,
     * generates database credentials, and migrates the tenant's database schema.
     * It also links the tenant storage, adds the tenant's domain to the tenants file,
     * adds the TenantServiceProvider to the application's providers list,
     * publishes the configuration file,
     * and formats the code using Laravel Pint if it is available.
     *
     * @param string $master The name of the master tenant to install.
     * @param string $domain The domain of the master tenant to install.
     * @param string $driver The database driver to use, default is 'pgsql'.
     * @param RollbackManager $rollback The rollback manager to use.
     * @param OutputStyle|null $output The output style to use (optional).
     *
     * @throws \Exception If an error occurs during the installation process.
     * @return void
     */
    public function prepareInstallation(string $master, string $domain, string $driver, RollbackManager $rollback, ?OutputStyle $output = null): void
    {
        try {
            Tenancy::validateData(
                [
                    'master' => $master,
                    'domain' => $domain,
                ],
                [
                    'master' => ['required', new TenantNameRule()],
                    'domain' => ['required', new DomainRule()],
                ]
            );

            // create master_tenant
            Artisan::call("orchestra:create $master --domain=$domain --driver=$driver --migrate");

            // update route files
            rollback_catch(
                function () use ($master, $rollback) {
                    Tenancy::removeRoutesDefaultContent(Tenancy::getRoutesDefaultContent('web'), base_path("site/$master/routes/web.php"));
                    $rollback->add(fn () => Tenancy::putRoutesDefaultContent(Tenancy::getRoutesDefaultContent('web'), base_path("site/$master/routes/web.php")));

                    Tenancy::removeRoutesDefaultContent(Tenancy::getRoutesDefaultContent('api'), base_path("site/$master/routes/api.php"));
                    $rollback->add(fn () => Tenancy::putRoutesDefaultContent(Tenancy::getRoutesDefaultContent('api'), base_path("site/$master/routes/api.php")));
                },
                $rollback
            );

            $output->info('Migration de la base de donnée effectuée.');

            rollback_catch(
                function () use ($output, $rollback) {
                    $this->addModuleProviderToAppConfig();
                    $output && runInConsole(fn () => $output->info('App\\Providers\\TenantServiceProvider::class ajouté au providers.'));
                    $rollback->add(fn () => $this->removeModuleProviderToAppConfig());
                },
                $rollback
            );


            rollback_catch(
                function () use ($output, $master, $domain, $rollback) {
                    $this->publishConfig($master, $domain);
                    $output && runInConsole(fn () => $output->info('Fichier de configuration publié.'));
                    $rollback->add(fn () => $this->unpublishConfig());
                },
                $rollback
            );

            rollback_catch(
                function () use ($output, $master, $rollback) {
                    Artisan::call("orchestra:autoload:add $master");
                    $output && runInConsole(fn () => $output->info('Configuration du composer effectuée'));
                    $rollback->add(fn () => Artisan::call("orchestra:autoload:remove $master"));
                },
                $rollback
            );

            rollback_catch(
                function () use ($rollback) {
                    $this->addTest();
                    $rollback->add(fn () => $this->removeTest());
                },
                $rollback
            );

            rollback_catch(
                function () use ($rollback) {
                    $this->makeSeeder();
                    $rollback->add(fn () => $this->removeSeeder());
                },
                $rollback
            );

            // format code
            \exec('./vendor/bin/pint ' . $this->getPintPaths(), $output, $status);
        } catch (\Exception $e) {
            throw new Exception($e, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Prepare the uninstallation process of the given master tenant.
     *
     * It is expected that the tenant has been already validated.
     *
     * @param string $master The name of the master tenant.
     * @param string $driver The database driver to use.
     * @param RollbackManager $rollback The rollback manager to use.
     * @param OutputStyle|null $output The output style to use (optional).
     *
     * @throws \Exception If there is a problem while uninstalling the tenant.
     */
    public function prepareUnInstallation(?string $master, string $driver, RollbackManager $rollback, ?OutputStyle $output): void
    {
        try {
            $domain = config('orchestra.master.domain');
            $master = $master ?? config('orchestra.master.name');

            if (! $domain && ! $master) {
                $output && runInConsole(fn () => $output->error('config file not found, create it before uninstalling'));
                $output && runInConsole(fn () => $output->writeln('name it orchestra.master.name and orchestra.master.domain'));
                $output && runInConsole(fn () => $output->writeln('peraps, it is already uninstalled'));

                return;
            }
            $master = parseTenantName($master);

            if (! File::exists(base_path("site/$master"))) {
                $output && runInConsole(fn () => $output->info('Already uninstalled'));

                return;
            }

            Artisan::call("orchestra:delete $master --driver=$driver");

            rollback_catch(
                function () use ($master, $rollback) {
                    $domain = config('orchestra.master.domain');
                    $this->removeModuleProviderToAppConfig();
                    $rollback->add(fn () => $this->addModuleProviderToAppConfig());

                    $this->unpublishConfig();
                    $domain != null && $rollback->add(fn () => $this->publishConfig($master, $domain));
                },
                $rollback
            );

            rollback_catch(
                function () use ($master, $rollback) {
                    Artisan::call("orchestra:autoload:remove $master");
                    $rollback->add(fn () => Artisan::call("orchestra:autoload:add $master"));
                },
                $rollback
            );


            rollback_catch(
                function () use ($rollback) {
                    $this->removeTest();
                    $rollback->add(fn () => $this->addTest());
                },
                $rollback
            );

            rollback_catch(
                function () use ($rollback) {
                    $this->removeSeeder();
                    $rollback->add(fn () => $this->makeSeeder());
                },
                $rollback
            );

            $output && runInConsole(fn () => $output->info('Uninstallation complete'));
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Add the TenantServiceProvider class to the application's providers list.
     *
     * If the file does not exist, it creates the file and adds the class to the list.
     * If the class is already in the list, it does nothing.
     *
     * @param string $providerClass The name of the class to add to the providers list. Defaults to App\Providers\TenantServiceProvider::class.
     *
     * @return void
     */
    public function addModuleProviderToAppConfig(string $providerClass = 'App\\Providers\\TenantServiceProvider::class'): void
    {
        $stub = module_path('.stub/Providers/TenantServiceProvider.php');
        // get providers directory
        if (! File::exists($providerDir = $this->getProviderDirectory('Providers'))) {
            File::makeDirectory($providerDir);
        };

        File::copy("$stub", "$providerDir/TenantServiceProvider.php");

        if (\file_exists(base_path('bootstrap/providers.php'))) {
            $app     = base_path('bootstrap/providers.php');
            $pattern = "/(return\s*\[\s*)(.*?)(^\s*\])/sm";
        } elseif (\file_exists(config_path('app.php'))) {
            $app     = config_path('app.php');
            $pattern = "/('providers'\s*=>\s*\[\s*)(.*?)(^\s*\])/sm";
        } else {
            return;
        }

        $content = \file_get_contents($app);

        if (\strpos($content, $providerClass) === false) {
            $injection = "$providerClass,\n";
            $content   = \preg_replace($pattern, "$1$injection$2$3", $content);
            \file_put_contents($app, $content);
            $this->addPintPath($app);
        }
    }

    /**
     * Publishes the Orchestra configuration file to the application's
     * configuration directory.
     *
     * The configuration file is published from the Orchestra module's
     * ".stub/config/orchestra.php" file to the application's
     * "config/orchestra.php" file.
     *
     * The published configuration file is modified to include the name and
     * domain of the master tenant.
     *
     * @param string $name The name of the master tenant.
     * @param string $domain The domain of the master tenant.
     */
    public function publishConfig(string $name, string $domain): void
    {
        $moduleOrchestra = module_path('.stub/config/orchestra.php');
        if (!File::exists(config_path('orchestra.php'))) {
            File::copy("$moduleOrchestra", config_path('orchestra.php'));
        }
        $configPath = config_path('orchestra.php');
        $content    = \file_get_contents($moduleOrchestra);

        $dpattern = "/('domain'\s*=>\s*)('.*?',$)/sm";
        // $npattern = "/('name'\s*=>\s*)('.*?',$)/sm";
        $npattern = "/('name'\s*=>\s*)('.*?',$)(.*)/sm";

        $content = \preg_replace($dpattern, "$1'$domain',", $content);
        $content = \preg_replace($npattern, "$1'$name',\n$3", $content);

        \file_put_contents($configPath, $content);

        $this->addPintPath($configPath);
    }

    /**
     * Removes the Orchestra configuration file from the application's
     * configuration directory.
     */
    public function unpublishConfig(): void
    {
        $configPath = config_path('orchestra.php');

        if (File::exists($configPath)) {
            File::delete($configPath);
        }
    }

    public function makeSeeder(): void
    {
        $seederPath        = 'seeders/Tenants/DatabaseSeeder.php';
        $targetPath        = database_path($seederPath);
        $moduleSeedersPath = module_path(".stub/database/{$seederPath}");

        // Vérifie et crée le dossier parent si besoin
        if (!File::exists(\dirname($targetPath))) {
            File::makeDirectory(\dirname($targetPath), 0777, true); // true pour récursif
        }

        // Copie si le fichier n'existe pas déjà
        if (!File::exists($targetPath)) {
            File::copy($moduleSeedersPath, $targetPath);
        }
    }

    public function removeSeeder(): void
    {
        $seederPath = 'seeders/Tenants/DatabaseSeeder.php';
        $targetPath = database_path($seederPath);

        if (File::exists($targetPath)) {
            File::delete($targetPath);
        }
    }

    /**
     * Removes the TenantServiceProvider from the application's providers list.
     *
     * It uses the $providerClass parameter to search for the line that contains
     * the TenantServiceProvider class in the application's providers list.
     * If the line is found, it is removed from the list.
     *
     * The provider is removed from the following files:
     * - bootstrap/providers.php (Laravel >= 8.0)
     * - config/app.php (Laravel < 8.0)
     *
     * If the file does not exist, the method does nothing.
     *
     * The method also formats the code using Laravel Pint if it is available.
     *
     * @param string $providerClass The class name of the provider to remove.
     *                               Defaults to 'App\\Providers\\TenantServiceProvider::class'.
     */
    public function removeModuleProviderToAppConfig(string $providerClass = 'App\\Providers\\TenantServiceProvider::class'): void
    {
        removeFileSecurely($this->getProviderDirectory('Providers') . '/TenantServiceProvider.php');

        $appConfigPath = \file_exists(base_path('bootstrap/providers.php'))
            ? base_path('bootstrap/providers.php')
            : (\file_exists(config_path('app.php')) ? config_path('app.php') : null);

        if (!$appConfigPath) {
            return;
        }

        $content = \file($appConfigPath); // Lecture ligne par ligne
        // $quoted = rtrim($providerClass, ':class'); // En cas de format :class
        $providerLinePattern = '/^\s*' . \preg_quote($providerClass, '/') . '\s*,?\s*$/';

        $filtered = \array_filter($content, function ($line) use ($providerLinePattern) {
            return !\preg_match($providerLinePattern, $line);
        });

        \file_put_contents($appConfigPath, \implode('', $filtered));

        // Formatter avec Laravel Pint si disponible
        if (\file_exists(base_path('vendor/bin/pint'))) {
            \exec('./vendor/bin/pint ' . \escapeshellarg($appConfigPath));
        }
    }

    /**
     * Renvoie le chemin du dossier des providers.
     *
     * @param  string  $provider
     * @return string
     */
    private function getProviderDirectory(string $provider): string
    {
        return app_path($provider);
    }

    /**
     * Add a path to be formatted by `pint` when formatting code.
     *
     * The path is added to an internal collection of paths to be formatted.
     * The collection is used by the `formatCode` method to format the code.
     *
     * @param string $path The path to add to the collection of paths to be formatted.
     *
     * @return static
     */
    private function addPintPath(string $path): static
    {
        $this->pintPaths->add(\escapeshellarg($path));

        return $this;
    }

    /**
     * Get the paths that should be formatted by `pint`.
     *
     * Returns a string of paths separated by spaces, or an empty string if the
     * collection of paths is empty.
     */
    private function getPintPaths(): string
    {
        if ($this->pintPaths->isEmpty()) {
            return '';
        }

        return \implode(' ', $this->pintPaths->toArray());
    }

    /**
     * Copies the test suite from the module stub to the application's
     * "tests/Feature" directory. It also updates the "phpunit.xml" file
     * with the MASTER_APP_URL, MASTER_APP_NAME, MASTER_DB_DATABASE,
     * SLAVE_APP_URL, SLAVE_APP_NAME, and SLAVE_DB_DATABASE environment
     * variables.
     *
     * The environment variables are set to the values in the config
     * file "config/orchestra.php".
     *
     * The method also saves the existing "phpunit.xml" and "tests/Pest.php"
     * files to the module stub directory with the ".saved" extension to
     * restore them after uninstall.
     *
     * @return void
     */
    private function addTest(): void
    {
        $bastPath = base_path();

        File::copyDirectory("{$this->moduleStubPath}/tests/Master", "{$bastPath}/tests/Feature/Master");
        File::copyDirectory("{$this->moduleStubPath}/tests/Slave", "{$bastPath}/tests/Feature/Slave");

        // get existing phpunit.xml content to restore it after uninstall
        if (File::exists("{$bastPath}/phpunit.xml")) {
            File::put("{$this->moduleStubPath}/phpunit.saved.xml", File::get("{$bastPath}/phpunit.xml"));
        }

        // get existing Pest.php content to restore it after uninstall
        if (File::exists("{$bastPath}/tests/Pest.php")) {
            File::put("{$this->moduleStubPath}/Pest.php.saved.stub", File::get("{$bastPath}/tests/Pest.php"));
        }

        // phpunit content
        File::put("{$bastPath}/phpunit.xml", File::get("{$this->moduleStubPath}/phpunit.xml"));

        $config = include config_path('orchestra.php');

        $masterDomain = $config['master']['domain'] ?? '';
        $masterName   = $config['master']['name']   ?? '';

        // add app_url and app_name
        $this->updatePhpUnitEnvVar("{$bastPath}/phpunit.xml", 'MASTER_APP_URL', "http://{$masterDomain}");
        $this->updatePhpUnitEnvVar("{$bastPath}/phpunit.xml", 'MASTER_APP_NAME', $masterName);
        $this->updatePhpUnitEnvVar("{$bastPath}/phpunit.xml", 'MASTER_DB_DATABASE', $masterName);

        $this->updatePhpUnitEnvVar("{$bastPath}/phpunit.xml", 'SLAVE_APP_URL', "http://{$masterName}slave.local");
        $this->updatePhpUnitEnvVar("{$bastPath}/phpunit.xml", 'SLAVE_APP_NAME', "{$masterName}slave");
        $this->updatePhpUnitEnvVar("{$bastPath}/phpunit.xml", 'SLAVE_DB_DATABASE', "{$masterName}slave");

        // Pest content
        $pestContent = File::get("{$this->moduleStubPath}/Pest.php.stub");
        $content     = <<<CONTENT
            <?php
            {$pestContent}
            CONTENT;

        File::put("{$bastPath}/tests/Pest.php", $content);
    }

    /**
     * Deletes the test suite added by the `addTest` method.
     *
     * It removes the "tests/Feature/Master" and "tests/Feature/Slave" directories and
     * restores the original "phpunit.xml" and "tests/Pest.php" files from the
     * module stub directory.
     *
     * @return void
     */
    private function removeTest(): void
    {
        $bastPath = base_path();

        File::deleteDirectory("{$bastPath}/tests/Feature/Master");
        File::deleteDirectory("{$bastPath}/tests/Feature/Slave");

        File::put("{$bastPath}/phpunit.xml", File::get("{$this->moduleStubPath}/phpunit.saved.xml"));

        // Pest content
        $pestContent = File::get("{$this->moduleStubPath}/Pest.php.saved.stub");
        $content     = <<<CONTENT
            {$pestContent}
            CONTENT;

        File::put("{$bastPath}/tests/Pest.php", $content);
    }

    private function updatePhpUnitEnvVar(string $filePath, string $envName, string $newValue): bool
    {
        if (!\file_exists($filePath)) {
            return false;
        }

        $dom                     = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput       = true;
        $dom->load($filePath);

        $envElements = $dom->getElementsByTagName('env');
        $found       = false;

        foreach ($envElements as $env) {
            if ($env->getAttribute('name') === $envName) {
                $env->setAttribute('value', $newValue);
                $found = true;
                break;
            }
        }

        if (!$found) {
            // Créer l'élément si non trouvé
            $php    = $dom->getElementsByTagName('php')->item(0);
            $newEnv = $dom->createElement('env');
            $newEnv->setAttribute('name', $envName);
            $newEnv->setAttribute('value', $newValue);
            $php->appendChild($newEnv);
        }

        return $dom->save($filePath) !== false;
    }
}
