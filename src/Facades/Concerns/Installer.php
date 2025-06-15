<?php

namespace Kjos\Orchestra\Facades\Concerns;

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
        $this->pintPaths = new Collection();
    }

    /**
     * Prepares the installation of Orchestra.
     *
     * It creates a master tenant, adds the TenantServiceProvider to the application's providers list,
     * publishes the Orchestra configuration file to the application's configuration directory,
     * and migrates the database for the master tenant.
     *
     * If the installation is successful, it formats the code using `pint`.
     *
     * @param string $master The name of the master tenant.
     * @param string $domain The domain of the master tenant.
     * @param OutputStyle|null $output An optional instance of `OutputStyle` to display information
     *                                about the installation process.
     *
     * @return void
     *
     * @throws Exception If an error occurs during the installation process.
     */
    public function prepareInstallation(string $master, string $domain, string $driver, ?OutputStyle $output = null)
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

            $output->info('Migration de la base de donnée effectuée.');

            $this->addModuleProviderToAppConfig();
            $output && runInConsole(fn () => $output->info('App\\Providers\\TenantServiceProvider::class ajouté au providers.'));

            $this->publishConfig($master, $domain);
            $output && runInConsole(fn () => $output->info('Fichier de configuration publié.'));

            Artisan::call("orchestra:autoload:add $master");
            $output && runInConsole(fn () => $output->info('Configuration du composer effectuée'));

            $output && runInConsole(fn () => $output->info('Installation complete'));

            // format code
            \exec('./vendor/bin/pint ' . $this->getPintPaths(), $output, $status);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Uninstalls Orchestra.
     *
     * It removes the master tenant, removes the TenantServiceProvider from the application's providers list,
     * unpublishes the Orchestra configuration file from the application's configuration directory,
     * and removes the Orchestra configuration file from the application's composer autoload.
     *
     * If the uninstallation is successful, it formats the code using `pint`.
     *
     * @param string $master The name of the master tenant.
     * @param string $driver The name of the driver to use for the uninstallation.
     * @param OutputStyle|null $output An optional instance of `OutputStyle` to display information
     *                                about the uninstallation process.
     *
     * @return void
     *
     * @throws Exception If an error occurs during the uninstallation process.
     */
    public function prepareUnInstallation(string $master, string $driver, ?OutputStyle $output): void
    {
        try {
            Tenancy::validateData(
                [
                    'master' => $master,
                ],
                [
                    'master' => ['required', new TenantNameRule()],
                ]
            );

            Artisan::call("orchestra:delete $master --driver=$driver");

            $this->removeModuleProviderToAppConfig();
            $this->unpublishConfig();

            Artisan::call("orchestra:autoload:remove $master");

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
        $npattern = "/('name'\s*=>\s*)('.*?',$)/sm";

        $content = \preg_replace($dpattern, "$1'$domain',", $content);
        $content = \preg_replace($npattern, "$1'$name',\n", $content);

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
}
