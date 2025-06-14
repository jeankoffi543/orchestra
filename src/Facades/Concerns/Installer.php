<?php

namespace Kjos\Orchestra\Facades\Concerns;

use Exception;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Kjos\Orchestra\Facades\OperaBuilder;
use Kjos\Orchestra\Rules\DomainRule;
use Kjos\Orchestra\Rules\TenantNameRule;

class Installer extends OperaBuilder
{
    public function prepareInstallation(string $master, string $domain): string
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

            $info = '';

            // create master_tenant
            Artisan::call('orchestra:create', [
               'name'     => $master,
               '--domain' => $domain,
               '--driver' => 'pgsql',
            ]);

            // get providers directory
            if (! File::exists($providerDir = $this->getProviderDirectory('Providers'))) {
                File::makeDirectory($providerDir);
            };

            File::copy("$this->moduleStubPath/TenantServiceProvider.php", "$providerDir/TenantServiceProvider.php");

            $hasNewBootstrap = \file_exists(\base_path('bootstrap/providers.php'));
            $hasOldConfig    = \file_exists(\config_path('app.php'));

            if ($hasNewBootstrap) {
                $info = "Ajoutez App\\Providers\\TenantServiceProvider::class dans bootstrap/providers.php sous 'providers'.";
            } elseif ($hasOldConfig) {
                $info = "Ajoutez App\\Providers\\TenantServiceProvider::class dans config/app.php sous 'providers'.";
            }

            Artisan::call("orchestra:autoload:add $master");

            $info .= PHP_EOL . PHP_EOL . 'Installation complete';

            return $info;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

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

            $hasNewBootstrap = \file_exists(\base_path('bootstrap/providers.php'));
            $hasOldConfig    = \file_exists(\config_path('app.php'));

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

    private function getProviderDirectory(string $provider): string
    {
        return \app_path($provider);
    }
}
