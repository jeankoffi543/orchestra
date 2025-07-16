<?php

namespace Kjos\Orchestra\Contexts;

use Illuminate\Support\Facades\Storage;
use Kjos\Orchestra\Facades\Oor;

class TenantStorageManager
{
    public static function disk(): \Illuminate\Filesystem\FilesystemAdapter|\Illuminate\Contracts\Filesystem\Filesystem
    {
        $tenant = Oor::getCurrent(); // Utilise ta méthode actuelle (session, middleware, etc.)

        $root = base_path("site/{$tenant}/storage/app/public");
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
        $target = base_path("site/{$tenant}/storage/app/public");
        $link   = public_path("storage/tenants/{$tenant}");

        if (!\file_exists($link)) {
            \symlink($target, $link);
        }
    }
}
