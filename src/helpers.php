<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Kjos\Orchestra\Facades\Concerns\RollbackManager;

if (!\function_exists('generatePassword')) {
    /**
     * Génère un mot de passe aléatoire de longueur spécifiée.
     *
     * Le mot de passe contient au moins un caractère de chaque type :
     * - Une majuscule
     * - Une minuscule
     * - Un chiffre
     * - Un caractère spécial (-_?)
     *
     * @param int $length La longueur du mot de passe souhaitée (par défaut : 12)
     * @return string Le mot de passe généré
     */
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
    /**
     * Normalize a tenant name.
     *
     * The normalized name is a lowercase, snake-cased string.
     *
     * @param string $name The tenant name to normalize.
     * @return string The normalized tenant name.
     */
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
    /**
     * Returns the base path of the site.
     *
     * If $name is not null, it returns the base path of the tenant with that name.
     * Otherwise, it returns the base path of the default site.
     *
     * @param string|null $name The name of the tenant.
     * @return string The base path of the site.
     */
    function getBasePath(?string $name = null): string
    {
        return $name ? base_path("site/$name") : base_path('site');
    }
}

if (!\function_exists('getEnvPath')) {
    /**
     * Returns the path of the .env file.
     *
     * If $name is not null, it returns the path of the .env file of the tenant with that name.
     * Otherwise, it returns the path of the default .env file.
     *
     * @param string|null $name The name of the tenant.
     * @return string The path of the .env file.
     */
    function getEnvPath(?string $name = null): string
    {
        return $name ? checkFileExists(base_path("site/$name/.env")) : checkFileExists(base_path('.env'));
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
        $env = $name ? getEnvPath("$name") : getEnvPath();

        return \parse_ini_file($env);
    }
}

if (!\function_exists('getStubPath')) {
    /**
     * Retourne le chemin du dossier .stub/.site.
     *
     * Si l'environnement est "testing", retourne le chemin du dossier .stub/.site du dossier racine
     * du package Orchestra, sinon retourne le chemin du dossier .stub/.site du dossier racine de l'application.
     *
     * @return string Le chemin du dossier .stub/.site.
     */
    function getStubPath(): string
    {
        return  app()->environment('testing') ? \dirname(__DIR__, 1) . '/.stub/.site' : base_path('.stub/.site');
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
    /**
     * Move a directory from one location to another, optionally overwriting the target.
     *
     * @param string $from The source directory path.
     * @param string $to The target directory path.
     * @param bool|null $overwrite Whether to overwrite the target directory if it exists (default is false).
     *
     * @throws Exception If the source directory does not exist.
     *
     * @return void
     */

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
    /**
     * Remove a file or directory securely.
     *
     * @param string $path The path to the file or directory to remove.
     *
     * @return void
     */

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
    /**
     * Get the secure path for a tenant.
     *
     * This function checks if the tenant's base path already exists. If it does,
     * an exception is thrown indicating that the tenant already exists.
     *
     * @param string $tenantName The name of the tenant for which the path is required.
     * @return string The secure base path for the tenant.
     *
     * @throws Exception If the tenant's path already exists.
     */

    function getTenantPathSecurely(string $tenantName): string
    {
        if (File::exists($destination = getBasePath($tenantName))) {
            throw new Exception("Le tenant '$tenantName' existe déjà.");
        }

        return $destination;
    }

    if (!\function_exists('checkFileExists')) {
        /**
         * Check if a file exists at the given path.
         *
         * @param string $path The path to check for the file's existence.
         *
         * @return string The path if the file exists.
         *
         * @throws Exception If the file does not exist at the given path.
         */

        function checkFileExists(string $path): string
        {
            if (!File::exists($path)) {
                throw new Exception("File does not exist: $path");
            }

            return $path;
        }
    }

    if (!\function_exists('getArchiveBase')) {
        /**
         * Get the path to the archive storage.
         *
         * @param string|null $fileName The name of the file to retrieve the path for.
         *
         * @return string The path to the archive storage.
         */
        function getArchiveBase(?string $fileName = null): string
        {
            return $fileName ? storage_path("app/private/archives/$fileName") : storage_path('app/private/archives');
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
            } elseif (File::isFile(checkFileExists($env))) {
                $env = \parse_ini_file($env);

                return $env['DB_CONNECTION'];
            } else {
                return 'pgsql';
            }
        }
    }
}


if (!\function_exists('clean_env_value')) {
    /**
     * Cleans an environment variable value by trimming surrounding quotes.
     *
     * @param string $value The environment variable value to clean.
     * @return string The cleaned environment variable value with surrounding quotes removed.
     */

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
    /**
     * Run the given callback in the console context.
     *
     * @param Closure $callback The callback to run in the console context.
     * @return void
     */
    function runInConsole(Closure $callback): void
    {
        if (app()->runningInConsole()) {
            $callback();
        }
    }
}

if (!\function_exists('module_path')) {
    /**
     * Return the path of the module.
     *
     * If $path is provided, it will be appended to the module path.
     * If $path is null, the module path will be returned as is.
     *
     * @param string|null $path The path to append to the module path.
     * @return string The module path.
     */
    function module_path(?string $path = null): string
    {
        return \dirname(__DIR__, 1) . ($path ? "/$path" : '');
    }
}

if (!\function_exists('getTeantPathIfExists')) {
    /**
     * Get the path of a tenant's directory if it exists.
     *
     * This function checks if the tenant's directory exists in the base path under "site".
     * If it exists, the function returns the path; otherwise, it returns null.
     *
     * @param string $name The name of the tenant.
     * @return string|null The path to the tenant's directory, or null if it does not exist.
     */

    function getTeantPathIfExists(string $name): ?string
    {
        $name = parseTenantName($name);
        if (File::exists($path = base_path("site/$name"))) {
            return $path;
        }

        return null;
    }
}

if (!\function_exists('rollback_catch')) {
    /**
      * Runs a callback and catches any exceptions thrown.
      * If an exception is caught, it runs the rollback and re-throws the exception.
      *
      * @param Closure $callback The callback to run.
      * @param RollbackManager $rollback The rollback manager to run if an exception is caught.
      * @return void
      * @throws Exception If an exception is caught.
      */
    function rollback_catch(Closure $callback, RollbackManager $rollback): void
    {
        try {
            $callback();
        } catch (\Exception $e) {
            $rollback->run();
            throw new \Exception($e->getMessage());
        }
    }
}
