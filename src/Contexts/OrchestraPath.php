<?php

namespace Kjos\Orchestra\Contexts;

class OrchestraPath
{
    public const TESTING_ROOT_PATH = 'framework/testing/site';
    public const SITE_ROOT_PATH    = 'site';
    public const SITE_PUBLIC_PATH  = 'storage/app/public';

    public static function tenant(string $tenant): string
    {
        return self::isTesting() ?
           storage_path(self::TESTING_ROOT_PATH . "/{$tenant}") :
           base_path(self::SITE_ROOT_PATH . "/{$tenant}");
    }

    public static function tenantStorage(string $tenant): string
    {
        return self::isTesting() ?
           storage_path(self::TESTING_ROOT_PATH . "/{$tenant}" . '/' . self::SITE_PUBLIC_PATH) :
           base_path(self::SITE_ROOT_PATH . "/{$tenant}");
    }

    public static function isTesting(): bool
    {
        return app()->environment('testing') || app()->runningUnitTests() || app()->runningInConsole();
    }
}
