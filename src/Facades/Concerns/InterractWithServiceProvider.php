<?php

namespace Kjos\Orchestra\Facades\Concerns;

use Illuminate\Support\Collection;
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
}
