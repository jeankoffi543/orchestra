<?php

namespace Kjos\Orchestra\Facades\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Kjos\Orchestra\Contexts\TenantStorageManager;
use Kjos\Orchestra\Facades\Oor;

trait InterractWithServiceProvider
{
    private function getSlavePath(Collection $route): ?string
    {
        if (app()->environment('testing')) {
            $path = storage_path('framework/testing/site/' . Oor::getCurrent() . '/routes/' . $route->get('file_name', 'api.php'));
        } else {
            $path = base_path('site/' . Oor::getCurrent() . '/routes/' . $route->get('file_name', 'api.php'));
        }

        return $path;
    }

    /**
     * @param array{name: string, prefix: string, middleware: string, file_name: string} $route
     * @return \Illuminate\Support\Collection<string, string>
     */
    private function routeToCollection(array $route): \Illuminate\Support\Collection
    {
        return collect($route);
    }

    public function saveFile($data, $path = '', $update = false, $fileKey = 'image', $model = null): array
    {
        $file = data_get($data, $fileKey);

        if (! $file instanceof UploadedFile) {
            if (! $file) {
                return $data;
            }

            $data[$fileKey] = $file;

            return $data;
        }

        if ($update && $model) {
            TenantStorageManager::disk()->delete($model->{$fileKey});
        }

        $fileName = Str::ulid() . '.' . $file->getClientOriginalExtension();

        TenantStorageManager::disk()->putFileAs($path, $file, $fileName);
        // $fullPath = TenantStorageManager::disk()->path(trim($path . '/' . $fileName, '/'));
        // dump($fullPath, file_exists($fullPath));
        $data[$fileKey] = TenantStorageManager::disk()->url($fileName);

        return $data;
    }

    public function removeFile($fileName): void
    {
        TenantStorageManager::disk()->delete($fileName);
    }
}
