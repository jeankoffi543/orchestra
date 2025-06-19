<?php

namespace Kjos\Orchestra\Contexts;

class TenantContext
{
    public string $name;

    /**
     * @var array<string, string>
     * */
    public array $env;

    /**
     * @var array<string, string>
     * */
    public array $config;

    /**
     * @param string $name
     * @param array<string, string> $env    Variables d'environnement du tenant
     * @param array<string, mixed>  $config Configuration du tenant
     */
    public function __construct(string $name, array $env, array $config)
    {
        $this->name   = $name;
        $this->env    = $env;
        $this->config = $config;
    }

    /**
     * Remplace les valeurs de configuration actuelles par celles du tenant.
     *
     * Ne modifie pas les variables d'environnement, mais
     * configure les valeurs de configuration en mémoire.
     */
    public function apply(): void
    {
        // Remplacer la config en mémoire, pas l'env du tenant courant
        foreach ($this->config as $key => $value) {
            config()->set($key, $value);
        }
    }
}
