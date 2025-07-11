<?php

namespace Kjos\Orchestra\Facades\Concerns;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;

trait CreateMasterApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $this->initHost();

        /** @phpstan-ignore-next-line */
        $app = require Application::inferBasePath() . '/bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->setDatabaseConnection();

        Artisan::call('db:create', [
           '--database'   => \getenv('MASTER_DB_DATABASE'),
           '--connection' => \getenv('MASTER_DB_CONNECTION'),
        ]);

        return $app;
    }

    protected function setDatabaseConnection(): void
    {
        $connection = \getenv('MASTER_DB_CONNECTION') ?: 'pgsql_master_test';
        config(['database.default' => $connection]);

        if (empty(config('app.key'))) {
            config(['app.key' => 'base64:' . \base64_encode(\random_bytes(32))]);
        }

        config([
           'database.connections.' . $connection . '.host'     => \getenv('MASTER_DB_HOST'),
           'database.connections.' . $connection . '.port'     => \getenv('MASTER_DB_PORT'),
           'database.connections.' . $connection . '.username' => \getenv('MASTER_DB_USERNAME'),
           'database.connections.' . $connection . '.password' => \getenv('MASTER_DB_PASSWORD'),
           'database.connections.' . $connection . '.driver'   => \getenv('MASTER_DB_DRIVER'),
           'database.connections.' . $connection . '.database' => \getenv('MASTER_DB_DATABASE'),
           // 'app.url'                                            => getenv('MASTER_APP_URL'),
        ]);
    }

    private function initHost(): void
    {
        $url = \getenv('MASTER_APP_URL');
        // Sync env
        $_SERVER['APP_URL'] = $url;
    }
}
