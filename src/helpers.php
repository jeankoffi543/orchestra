<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

if (!\function_exists('generatePassword')) {
    function generatePassword(int $length = 12): string
    {
        $upperCase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowerCase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers   = '0123456789';
        // $specialChars = '!@%^&*()-_=+[]{}|;:,.<>?';
        $specialChars = '-_?';

        // Assurer au moins un caractère de chaque type
        $password = [
            $upperCase[\random_int(0, \strlen($upperCase) - 1)],
            $lowerCase[\random_int(0, \strlen($lowerCase) - 1)],
            $numbers[\random_int(0, \strlen($numbers) - 1)],
            $specialChars[\random_int(0, \strlen($specialChars) - 1)],
        ];

        // Compléter le reste aléatoirement
        $allChars = $upperCase . $lowerCase . $numbers . $specialChars;
        for ($i = 4; $i < $length; $i++) {
            $password[] = $allChars[\random_int(0, \strlen($allChars) - 1)];
        }

        // Mélanger le mot de passe
        return \str_shuffle(\implode('', $password));
    }
}

if (!\function_exists('parseTenantName')) {
    function parseTenantName(string $name): string
    {
        return Str::snake($name);
    }
}

if (!\function_exists('generateDatabaseCredentials')) {
    /**
     * Génère les informations de connexion pour une base de données.
     *
     * Le nom de la base de données est limité à 15 caractères.
     *
     * @param string $name Nom du tenant.
     *
     * @return array<string, string> Les informations de connexion.
     */
    function generateDatabaseCredentials(string $name): array
    {
        $dbName = "db___$name"; // Limite à 15 caractères
        $dbUser = "user_____$name";

        return [
            'DB_DATABASE' => $dbName,
            'DB_USERNAME' => $dbUser,
        ];
    }
}

if (!\function_exists('getBasePath')) {
    function getBasePath(?string $name = null): string
    {
        return $name ? \base_path("site/$name") : \base_path('site');
    }
}

if (!\function_exists('getEnvPath')) {
    function getEnvPath(?string $name = null): string
    {
        return $name ? \checkFileExists(\base_path("site/$name/.env")) : \checkFileExists(\base_path('.env'));
    }
}

if (!\function_exists('parseEnvPath')) {
    /**
     * Lit le fichier .env et le retourne sous forme de tableau associatif.
     *
     * Si `$name` est fourni, lit le fichier .env du tenant correspondant.
     *
     * @param string|null $name Nom du tenant.
     *
     * @return array<string, string> Les clés/valeurs du fichier .env.
     */
    function parseEnvPath(?string $name = null): array
    {
        $env = $name ? \getEnvPath("$name") : \getEnvPath();

        return \parse_ini_file($env);
    }
}

if (!\function_exists('getStubPath')) {
    function getStubPath(): string
    {
        return  \app()->environment('testing') ? \dirname(__DIR__, 1) . '/.stub/.site' : \base_path('.stub/.site');
    }
}

if (!\function_exists('createDirectorySecurely')) {
    /**
     * Cr e un dossier s r s ment.
     *
     * @param string $path Chemin du dossier   cr er.
     * @param int|null $recursively Niveau de r cursivit .
     */
    function createDirectorySecurely(string $path, ?int $recursively = null): void
    {
        File::ensureDirectoryExists($path, $recursively);
    }
}

if (!\function_exists('moveDirectorySecurely')) {
    function moveDirectorySecurely(string $from, string $to, ?bool $overwrite = false): void
    {
        if (File::exists($from)) {
            File::moveDirectory($from, $to, $overwrite);
        } else {
            throw new Exception("'$from' existe déjà.");
        }
    }
}

if (!\function_exists('removeFileSecurely')) {
    function removeFileSecurely(string $path): void
    {
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        } elseif (File::isFile($path)) {
            File::delete($path);
        }
    }
}

if (!\function_exists('getTenantPathSecurely')) {
    function getTenantPathSecurely(string $tenantName): string
    {
        if (File::exists($destination = \getBasePath($tenantName))) {
            throw new Exception("Le tenant '$tenantName' existe déjà.");
        }

        return $destination;
    }

    if (!\function_exists('checkFileExists')) {
        function checkFileExists(string $path): string
        {
            if (!File::exists($path)) {
                throw new Exception("File does not exist: $path");
            }

            return $path;
        }
    }

    if (!\function_exists('getArchiveBase')) {
        function getArchiveBase(?string $fileName = null): string
        {
            return $fileName ? \storage_path("app/private/archives/$fileName") : \storage_path('app/private/archives');
        }
    }

    if (!\function_exists('getDriver')) {
        /**
         * Determines the database driver based on the provided environment configuration.
         *
         * @param string|array<string, string> $env The environment configuration, either as a file path or an array.
         *                          If a string is provided, it is expected to be a file path to a .env file.
         *                          If an array is provided, it should contain configuration keys.
         *
         * @return string The database connection driver, such as 'pgsql' or 'mysql'.
         *                Defaults to 'pgsql' if no driver is specified or found.
         */

        function getDriver(string|array $env): string
        {
            if (\is_array($env) && \array_key_exists('DB_CONNECTION', $env)) {
                return $env['DB_CONNECTION'];
            } elseif (File::isFile(\checkFileExists($env))) {
                $env = \parse_ini_file($env);

                return $env['DB_CONNECTION'];
            } else {
                return 'pgsql';
            }
        }
    }
}


if (!\function_exists('clean_env_value')) {
    function clean_env_value(string $value): string
    {
        return \trim($value, "\"'");
    }
}

if (!\function_exists('safeShell')) {
    /**
     * Protège une valeur selon le contexte pour une commande shell.
     *
     * @param string $value La valeur à protéger
     * @param string $context 'arg', 'env', ou 'file'
     * @return string
     */
    function safeShell(string $value, string $context = 'arg'): string
    {
        return match ($context) {
            // Pour les arguments shell comme -U utilisateur, -d database (sans quotes)
            'arg' => \escapeshellcmd($value),

            // Pour des assignations comme PGPASSWORD="valeur" (entouré de quotes simples)
            'env' => \escapeshellarg($value),

            // Pour des chemins de fichier, dans redirection > ou <
            'file' => \escapeshellarg($value),

            default => throw new InvalidArgumentException("Contexte shell non supporté : $context")
        };
    }
}

if (!\function_exists('runInConsole')) {
    function runInConsole(mixed $callback = null): void
    {
        if (\app()->runningInConsole()) {
            \is_callable($callback) ? $callback() : $callback;
        }
    }
}
