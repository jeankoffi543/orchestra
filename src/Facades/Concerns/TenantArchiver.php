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
    public static function archiveTenant(string $tenantName, string $driver = 'pgsql', ?OutputStyle $console = null): string
    {
        try {
            $timestamp   = \now()->format('Ymd_His');
            $archiveBase = \getArchiveBase("{$tenantName}_{$timestamp}");

            // 1. Créer le dossier
            \createDirectorySecurely($archiveBase);

            // 2. Déplacer le dossier site
            $sitePath         = \checkFileExists(\getBasePath($tenantName));
            $archivedSitePath = "{$archiveBase}/site";

            \moveDirectorySecurely($sitePath, $archivedSitePath);

            // 3. dump data base
            $envPath = \checkFileExists("{$archivedSitePath}");
            TenantDatabaseManager::dump($envPath, $archiveBase, $console, $driver);

            // 4. Création du zip
            return self::zip($archiveBase, $console);
        } catch (Exception $e) {
            Tenancy::rollback($tenantName, deleteTenant: false);
            throw new Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public static function restoreTenant(string $tenantName, string $driver = 'pgsql', ?OutputStyle $console = null): void
    {
        try {
            // get ziping file from tenant name
            $zipPath = self::getTenantZippingFileFromName($tenantName, true);
            if (self::isZipOpenable($zipPath)) {
                $extractPath = self::unzip($zipPath, $console);

                // 2. Récupération des chemins
                $sitePath = \checkFileExists("$extractPath/site");
                $envPath  = \checkFileExists("$sitePath/.env");

                // 3 restaurer la base de données
                TenantDatabaseManager::import($envPath, $extractPath, $console, $driver);

                // 4. Restaurer le dossier du tenant
                \moveDirectorySecurely($sitePath, $destination = \getBasePath($tenantName));
                \runInConsole(fn () => $console?->writeln("<info>Dossier du tenant restauré vers : $destination</info>"));


                // 5. Nettoyer l'extraction temporaire
                \removeFileSecurely($zipPath);
                \removeFileSecurely($extractPath);
                \runInConsole(fn () => $console?->writeln("<info>Nettoyage effectué : $extractPath supprimé</info>"));
            }
        } catch (\Exception $e) {
            throw new Exception('Erreur lors de la restauration du tenant : ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public static function removeZip(string $tenantName): void
    {
        try {
            // get ziping file from tenant name
            $zipPath = self::getTenantZippingFileFromName($tenantName, true);
            \removeFileSecurely($zipPath);
        } catch (\Exception $e) {
            throw new Exception('Erreur lors de la restauration du tenant : ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

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

                \runInConsole(fn () => $console?->writeln("<info>Tenant archivé avec succès : $zipPath</info>"));

                return $zipPath;
            } else {
                throw new Exception('Impossible de créer l’archive ZIP');
            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public static function unzip(string $archiveBase, ?OutputStyle $console = null): ?string
    {
        try {
            $zip = new ZipArchive();
            if ($zip->open($archiveBase) === true) {
                $extractPath = \str_replace('.zip', '', $archiveBase);

                // 1. Dézipper
                $zip->extractTo($extractPath);
                $zip->close();
                \runInConsole(fn () => $console?->writeln("<info>Archive extraite vers : $extractPath</info>"));
            } else {
                throw new Exception('Impossible de créer l’archive ZIP');
            }

            return $extractPath;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

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

    public static function getTenantZippingFileFromName(string $tenantName, ?bool $withbasePath = false): ?string
    {
        $archiveBase  = \checkFileExists(\getArchiveBase());
        $fileIterator = Tenancy::fileIterator($archiveBase);

        foreach ($fileIterator as $file) {
            if (self::isZipOpenable($file) && self::extractTenantName($file->getFilename(), $tenantName)) {
                return $withbasePath ? $file->getRealPath() : $file->getFilename();
            }
        }

        return null;
    }

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
