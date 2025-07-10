<?php

namespace Kjos\Orchestra\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Kjos\Orchestra\Facades\Concerns\Tenancy;

class VritualHost extends Deployer
{
    protected ?string $name;
    protected ?string $domain;
    protected string $basePath;
    private string $appacheConfig;
    private string $savedConfPath;
    private string $confPrefix;

    public function __construct(?string $name = null, ?string $domain = null)
    {
        parent::__construct();
        $this->name     = $name ? parseTenantName($name) : '';
        $this->domain   = $domain;
        $this->basePath = base_path("site/{$this->name}");
        $this->setName($name);
        $this->setDomain($domain);
        $this->appacheConfig = '/etc/apache2/sites-available';
        $this->confPrefix    = config('orchestra.virtual_hosts_config_prefix') ?? 'orchestra-';
        $this->savedConfPath = base_path("site/{$this->name}/storage/app/private/{$this->confPrefix}{$this->domain}.conf");
    }

    public function setName(?string $name): void
    {
        $this->name = parseTenantName($name ?? '');
    }

    public function setDomain(?string $domain): void
    {
        $this->domain = $domain;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function scanner(): void
    {
        // Log::info('Scanning virtual hosts...');
        try {
            $domains = collect(Tenancy::getDomains());
            $vhosts  = collect($this->vhostSites());
            // domains who are not vhosts
            $domains->filter(fn ($d) => ! $vhosts->contains($d))
                ->each(function ($domain, $key) {
                    $this->name          = parseTenantName($key);
                    $this->domain        = $domain;
                    $this->savedConfPath = base_path("site/{$this->name}/storage/app/private/{$this->confPrefix}{$this->domain}.conf");
                    $this->basePath      = base_path("site/{$this->name}");
                    $this->create();
                });
            // clean orphans sites
            $this->cleanOrphans();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function cleanOrphans(): void
    {
        $vhosts  = collect($this->vhostSites());
        $domains = collect(Tenancy::getDomains());
        $vhosts->filter(fn ($d) => ! $domains->contains($d))
            ->each(function ($domain, $key) {
                $this->name   = parseTenantName($key);
                $this->domain = $domain;
                $this->remove();
            });
    }

    /**
     * List all the domains that are already configured as virtual hosts.
     *
     * @return array<string>|null
     */
    public function vhostSites(): ?array
    {
        $confDomains = [];
        $configs     = Tenancy::fileIterator($this->appacheConfig);
        foreach ($configs as $config) {
            if (\str_starts_with($fileName = $config->getFilename(), $this->confPrefix)) {
                $confDomains[] = \preg_replace("/^{$this->confPrefix}(.*)\.conf$/", '$1', $fileName);
            }
        }

        return $confDomains;
    }

    public function create(): void
    {
        if (File::exists($this->scriptPath)) {
            $content = $this->generateVirtualHost($this->domain, public_path(), $this->name);

            // \file_put_contents($this->savedConfPath, $content);

            $tempFile = \tempnam(\sys_get_temp_dir(), 'vhost_');
            \file_put_contents($tempFile, $content);
            $this->addCode("sudo mv {$tempFile} {$this->savedConfPath}");

            $basePath = base_path();
            $this->addCode("$this->scriptPath deploy --conf {$this->savedConfPath} --name {$this->name} --domain {$this->domain} --base-path {$basePath} --cron-log {$this->cronLogPath}")
                ->execute();

            if (\file_exists($tempFile)) {
                \unlink($tempFile);
            }
        }
    }

    public function remove(?string $domain = null): void
    {
        if ($domain) {
            $this->domain = $domain;
        }
        if (File::exists($this->scriptPath)) {
            $basePath = base_path();
            $this->addCode("$this->scriptPath remove --conf {$this->confPrefix}{$this->domain}.conf --domain {$this->domain} --base-path {$basePath} --cron-log {$this->cronLogPath}")
                ->execute();

            removeFileSecurely($this->cronLogPath ?? '');
        }
    }

    public function generateVirtualHost(string $domain, ?string $documentRoot = null, ?string $serverName = null): string
    {
        $domain      = \strtolower(\trim($domain));
        $serverName  = $domain;
        $serverAlias = "www.{$domain}";
        $logDir      = "/var/log/apache2/{$domain}";
        $docRoot     = $documentRoot ?? public_path();

        return <<<CONF
         <VirtualHost *:80>
            ServerName {$serverName}
            ServerAlias {$serverAlias}

            DocumentRoot {$docRoot}
            <Directory {$docRoot}>
               Options Indexes FollowSymLinks
               AllowOverride All
               Require all granted
            </Directory>

            ErrorLog {$logDir}/error.log
            CustomLog {$logDir}/access.log combined

            # Optional: Redirect to HTTPS
            # Redirect permanent / https://{$serverName}/

            # Enable PHP if needed (for mod_php)
            <FilesMatch "\.php$">
               SetHandler application/x-httpd-php
            </FilesMatch>
         </VirtualHost>
      CONF;
    }
}
