<?php

namespace Kjos\Orchestra\Contexts;

use Illuminate\Support\Facades\Storage;
use Kjos\Orchestra\Facades\Oor;

class TenantStorageManager
{
    public static function disk(): \Illuminate\Filesystem\FilesystemAdapter|\Illuminate\Contracts\Filesystem\Filesystem
    {
        $tenant = Oor::getCurrent(); // Utilise ta méthode actuelle (session, middleware, etc.)

        $root = OrchestraPath::tenantStorage($tenant);
        $url  = config('app.url') . "/storage/tenants/{$tenant}";

        if (!\is_dir($root)) {
            \mkdir($root, 0777, true);
        }

        self::ensureSymlinkExists($tenant);

        return Storage::build([
            'driver'     => 'local',
            'root'       => $root,
            'url'        => $url,
            'visibility' => 'public',
        ]);
    }

    protected static function ensureSymlinkExists(string $tenant): void
    {
        $target = OrchestraPath::tenantStorage($tenant);
        $link   = public_path("storage/tenants/{$tenant}");

        // Créer le dossier s’il n'existe pas/ si on est en test
        if (!\file_exists(\dirname($link))) {
            \mkdir(\dirname($target), 0777, true);
        }

        // on stop si on est en test
        if (OrchestraPath::isTesting()) {
            return;
        }

        if (!\file_exists(\dirname($link))) {
            \mkdir(\dirname($link), 0777, true);
        }

        // Vérifie que le fichier cible existe ou non
        if (\file_exists($target) && !\file_exists($link)) {
            \symlink($target, $link);
        }


        if (!\file_exists($link)) {
            \symlink($target, $link);
        }
    }
}
