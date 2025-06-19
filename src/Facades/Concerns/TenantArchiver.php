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
     * Archives a tenant's site and database.
     *
     * This function creates a timestamped archive of a tenant's site directory and
     * database. It securely creates a directory, moves the tenant's site directory
     * to the archive location, and dumps the database. A ZIP archive is then created
     * from the archived directory. Rollback procedures are implemented to undo
     * actions in case of failure.
     *
     * @param string $tenantName The name of the tenant to archive.
     * @param string $driver The database driver to use for dumping the database. Defaults to 'pgsql'.
     * @param RollbackManager $rollback A manager to handle rollback operations in case of failure.
     * @param OutputStyle|null $console An optional console output interface for logging messages.
     *
     * @return string The path to the created ZIP file.
     *
     * @throws Exception If any error occurs during the archiving process.
     */
    public static function archiveTenant(string $tenantName, RollbackManager $rollback, string $driver = 'pgsql', ?OutputStyle $console = null): ?string
    {
        try {
            $timestamp   = now()->format('Ymd_His');
            $archiveBase = getArchiveBase("{$tenantName}_{$timestamp}");

            // 1. Créer le dossier
            rollback_catch(
                function () use ($archiveBase, $rollback) {
                    createDirectoryIfNotExists($archiveBase);

                    $rollback->add(fn () => removeFileSecurely($archiveBase));
                },
                $rollback
            );



            // 2. Déplacer le dossier site
            $archivedSitePath = "{$archiveBase}/site";
            $sitePath         = checkFileExists(getBasePath($tenantName));
            if ($sitePath) {
                rollback_catch(
                    function () use ($archivedSitePath, $sitePath, $rollback) {
                        moveDirectorySecurely($sitePath, $archivedSitePath);
                        $rollback->add(fn () => moveDirectorySecurely($archivedSitePath, $sitePath));
                    },
                    $rollback
                );
            }

            // 3. dump data base
            $envPath = checkFileExists("{$archivedSitePath}");
            if ($envPath) {
                rollback_catch(
                    function () use ($envPath, $archiveBase, $console, $driver, $rollback) {
                        TenantDatabaseManager::dump($envPath, $archiveBase, $console, $driver);
                        $rollback->add(fn () => TenantDatabaseManager::import($envPath, $archiveBase, $console, $driver));
                    },
                    $rollback
                );
            }

            // 4. Création du zip
            $zip = null;
            rollback_catch(
                function () use ($archiveBase, $console, &$zip, $rollback) {
                    $zip = self::zip($archiveBase, $console);
                    $rollback->add(fn () => self::unzip("$archiveBase.zip", $console));
                },
                $rollback
            );

            return $zip;
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restores a tenant's site and database from a ZIP archive.
     *
     * This function retrieves and extracts a tenant's ZIP archive, restores the
     * site directory and database, and performs cleanup of temporary files.
     * Rollback procedures are implemented to undo actions in case of failure.
     *
     * @param string $tenantName The name of the tenant to restore.
     * @param string $driver The database driver to use for importing the database. Defaults to 'pgsql'.
     * @param RollbackManager $rollback A manager to handle rollback operations in case of failure.
     * @param OutputStyle|null $console An optional console output interface for logging messages.
     *
     * @throws Exception If any error occurs during the restoration process.
     */
    public static function restoreTenant(string $tenantName, RollbackManager $rollback, string $driver = 'pgsql', ?OutputStyle $console = null): void
    {
        try {
            // get ziping file from tenant name
            $zipPath = self::getTenantZippingFileFromName($tenantName, true);
            if (self::isZipOpenable($zipPath)) {
                rollback_catch(
                    function () use ($zipPath, $console, &$extractPath, $rollback) {
                        $extractPath = self::unzip($zipPath, $console);
                        $rollback->add(fn () => self::zip(\trim($zipPath, '.zip'), $console));
                    },
                    $rollback
                );

                // 2. Récupération des chemins
                $sitePath = checkFileExists("$extractPath/site");
                $envPath  = checkFileExists("$sitePath/.env");

                // 3 restaurer la base de données
                rollback_catch(
                    function () use ($envPath, $console, $extractPath, $driver, $rollback) {
                        TenantDatabaseManager::import($envPath, $extractPath, $console, $driver);
                        $rollback->add(fn () => TenantDatabaseManager::dump($envPath, $extractPath, $console, $driver));
                    },
                    $rollback
                );


                // 4. Restaurer le dossier du tenant
                rollback_catch(
                    function () use ($sitePath, $console, $tenantName, $rollback) {
                        moveDirectorySecurely($sitePath, $destination = getBasePath($tenantName));
                        runInConsole(fn () => $console?->writeln("<info>Dossier du tenant restauré vers : $destination</info>"));
                        $rollback->add(fn () => moveDirectorySecurely($destination, $sitePath));
                    },
                    $rollback
                );

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
