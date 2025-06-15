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
     * Uninstall Orchestra from the current application.
     *
     * It removes the master tenant and the TenantServiceProvider from the providers list.
     *
     * @param string $master the name of the master tenant
     *
     * @return string a message indicating the uninstallation is complete
     *
     * @throws \Exception if an error occurs during the uninstallation process
     */
    public function prepareUnInstallation(string $master): string
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

            $info = '';

            // create master_tenant
            Artisan::call('orchestra:delete', [
                'name'     => $master,
                '--driver' => 'pgsql',
            ]);
            // get providers directory
            if (File::exists($providerFile = $this->getProviderDirectory('Providers') . '/TenantServiceProvider.php')) {
                File::delete($providerFile);
            };

            $hasNewBootstrap = \file_exists(base_path('bootstrap/providers.php'));
            $hasOldConfig    = \file_exists(config_path('app.php'));

            if ($hasNewBootstrap) {
                $info = "Retirrez manuellement App\\Providers\\TenantServiceProvider::class dans bootstrap/providers.php sous 'providers'.";
            } elseif ($hasOldConfig) {
                $info = "Retirrez manuellement App\\Providers\\TenantServiceProvider::class dans config/app.php sous 'providers'.";
            }

            Artisan::call("orchestra:autoload:remove $master");

            $info .= PHP_EOL . PHP_EOL . 'Uninstallation complete';

            return $info;
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
