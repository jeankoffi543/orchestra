<?php

namespace Kjos\Orchestra\Facades\Concerns;

use Exception;
use Illuminate\Console\OutputStyle;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Kjos\Orchestra\Services\TenantDatabaseManager;
use ZipArchive;

class TenantArchiver
{
    /**
     * Archive a tenant by creating a zip archive containing the tenant's site
     * directory and a dump of its database.
     *
     * @param string $tenantName the name of the tenant to archive
     * @param string $driver the database driver to use (default is 'pgsql')
     * @param OutputStyle|null $console the console output (default is null)
     * @return string the path to the archive file
     *
     * @throws Exception if there is a problem while archiving the tenant
     */
    public static function archiveTenant(string $tenantName, string $driver = 'pgsql', ?OutputStyle $console = null): string
    {
        try {
            $timestamp   = now()->format('Ymd_His');
            $archiveBase = getArchiveBase("{$tenantName}_{$timestamp}");

            // 1. Créer le dossier
            createDirectorySecurely($archiveBase);

            // 2. Déplacer le dossier site
            $sitePath         = checkFileExists(getBasePath($tenantName));
            $archivedSitePath = "{$archiveBase}/site";

            moveDirectorySecurely($sitePath, $archivedSitePath);

            // 3. dump data base
            $envPath = checkFileExists("{$archivedSitePath}");
            TenantDatabaseManager::dump($envPath, $archiveBase, $console, $driver);

            // 4. Création du zip
            return self::zip($archiveBase, $console);
        } catch (Exception $e) {
            Tenancy::rollback($tenantName, deleteTenant: false);
            throw new Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restore a tenant from an archive.
     *
     * 1. Get ziping file from tenant name
     * 2. Unzip the file
     * 3. Restore the database
     * 4. Restore the tenant directory
     * 5. Clean up the temporary extraction
     *
     * @param string $tenantName The name of the tenant to restore.
     * @param string $driver The database driver to use.
     * @param OutputStyle|null $console The console output style.
     *
     * @throws \Exception If the tenant is not found or if there is a problem while restoring it.
     */
    public static function restoreTenant(string $tenantName, string $driver = 'pgsql', ?OutputStyle $console = null): void
    {
        try {
            // get ziping file from tenant name
            $zipPath = self::getTenantZippingFileFromName($tenantName, true);
            if (self::isZipOpenable($zipPath)) {
                $extractPath = self::unzip($zipPath, $console);

                // 2. Récupération des chemins
                $sitePath = checkFileExists("$extractPath/site");
                $envPath  = checkFileExists("$sitePath/.env");

                // 3 restaurer la base de données
                TenantDatabaseManager::import($envPath, $extractPath, $console, $driver);

                // 4. Restaurer le dossier du tenant
                moveDirectorySecurely($sitePath, $destination = getBasePath($tenantName));
                runInConsole(fn () => $console?->writeln("<info>Dossier du tenant restauré vers : $destination</info>"));


                // 5. Nettoyer l'extraction temporaire
                removeFileSecurely($zipPath);
                removeFileSecurely($extractPath);
                runInConsole(fn () => $console?->writeln("<info>Nettoyage effectué : $extractPath supprimé</info>"));
            }
        } catch (\Exception $e) {
            throw new Exception('Erreur lors de la restauration du tenant : ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprime le fichier zip lié au tenant.
     *
     * @param string $tenantName Le nom du tenant.
     *
     * @throws \Exception Si une erreur survient lors de la suppression.
     */
    public static function removeZip(string $tenantName): void
    {
        try {
            // get ziping file from tenant name
            $zipPath = self::getTenantZippingFileFromName($tenantName, true);
            removeFileSecurely($zipPath);
        } catch (\Exception $e) {
            throw new Exception('Erreur lors de la restauration du tenant : ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a ZIP archive from the specified directory.
     *
     * This function compresses the contents of the given directory into a ZIP file.
     * After successfully creating the ZIP archive, the original directory is deleted.
     *
     * @param string $archiveBase The base directory to compress into a ZIP archive.
     * @param OutputStyle|null $console An optional console output interface for logging messages.
     *
     * @return string The path to the created ZIP file.
     *
     * @throws Exception If the ZIP archive cannot be created or there is an error during the process.
     */
    public static function zip(string $archiveBase, ?OutputStyle $console = null): string
    {
        try {
            $zipPath = "{$archiveBase}.zip";
            $zip     = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
                foreach (Tenancy::fileIterator($archiveBase) as $file) {
                    $filePath     = $file->getRealPath();
                    $relativePath = Str::after($filePath, $archiveBase . DIRECTORY_SEPARATOR);
                    $zip->addFile($filePath, $relativePath);
                }

                $zip->close();
                File::deleteDirectory($archiveBase);

                runInConsole(fn () => $console?->writeln("<info>Tenant archivé avec succès : $zipPath</info>"));

                return $zipPath;
            } else {
                throw new Exception('Impossible de créer l’archive ZIP');
            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Unzip the given archive and extract its contents.
     *
     * This function attempts to open the specified ZIP archive,
     * extract its contents to a directory with the same name as the archive
     * (excluding the .zip extension), and then close the archive.
     * It also optionally logs the extraction process in the console.
     *
     * @param string $archiveBase The path to the ZIP archive to be extracted.
     * @param OutputStyle|null $console An optional console output instance for logging.
     *
     * @return string|null The path where the archive was extracted, or null if extraction fails.
     *
     * @throws Exception If the archive cannot be opened or another error occurs during extraction.
     */
    public static function unzip(string $archiveBase, ?OutputStyle $console = null): ?string
    {
        try {
            $zip = new ZipArchive();
            if ($zip->open($archiveBase) === true) {
                $extractPath = \str_replace('.zip', '', $archiveBase);

                // 1. Dézipper
                $zip->extractTo($extractPath);
                $zip->close();
                runInConsole(fn () => $console?->writeln("<info>Archive extraite vers : $extractPath</info>"));
            } else {
                throw new Exception('Impossible de créer l’archive ZIP');
            }

            return $extractPath;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Determine if the given path is a valid zip file that can be opened.
     *
     * @param string|null $path
     *
     * @return bool
     */
    public static function isZipOpenable(?string $path): bool
    {
        if (!\file_exists($path)) {
            return false;
        }

        $zip    = new ZipArchive();
        $result = $zip->open($path);
        $zip->close();

        return $result === true;
    }

    /**
     * Retrieve the name or path of the tenant's zip file.
     *
     * This function iterates over files in the archive base directory,
     * checking each file to see if it is a zip file and if its name matches
     * the given tenant name. If both conditions are satisfied, the function
     * returns either the full path or just the filename of the zip file,
     * depending on the value of the $withbasePath parameter.
     *
     * @param string $tenantName The name of the tenant whose zip file is to be retrieved.
     * @param bool|null $withbasePath Whether to return the full file path (true) or just the filename (false).
     *                                Defaults to false.
     *
     * @return string|null The filename or full path of the tenant's zip file, or null if no matching file is found.
     */
    public static function getTenantZippingFileFromName(string $tenantName, ?bool $withbasePath = false): ?string
    {
        $archiveBase  = checkFileExists(getArchiveBase());
        $fileIterator = Tenancy::fileIterator($archiveBase);

        foreach ($fileIterator as $file) {
            if (self::isZipOpenable($file) && self::extractTenantName($file->getFilename(), $tenantName)) {
                return $withbasePath ? $file->getRealPath() : $file->getFilename();
            }
        }

        return null;
    }

    /**
     * Check if the given $archiveName matches the given $tenantName.
     *
     * The $archiveName can be the name of a zip file, or the name of a folder.
     * The $tenantName is the name of the tenant.
     *
     * The function will return true if the $tenantName matches the $archiveName
     * after removing the timestamp and the .zip extension.
     *
     * @param string $archiveName The name of the zip file or folder.
     * @param string $tenantName The name of the tenant.
     *
     * @return bool True if the $tenantName matches the $archiveName, otherwise false.
     */
    public static function extractTenantName(string $archiveName, string $tenantName): bool
    {
        $name1 = \preg_replace('/_\d{8}_\d{6}\.zip$/', '', $archiveName);
        $name2 = \preg_replace('/_\d{8}_\d{6}$/', '', $archiveName);

        return match ($tenantName) {
            $name1  => $tenantName === $name1,
            $name2  => $tenantName === $name2,
            default => false,
        };
    }
}
