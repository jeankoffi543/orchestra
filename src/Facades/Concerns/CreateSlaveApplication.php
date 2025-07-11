<?php

namespace Kjos\Orchestra\Facades\Concerns;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;

trait CreateSlaveApplication
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
           '--database'   => \getenv('SLAVE_DB_DATABASE'),
           '--connection' => \getenv('SLAVE_DB_CONNECTION'),
        ]);

        return $app;
    }

    protected function setDatabaseConnection(): void
    {
        $connection = \getenv('SLAVE_DB_CONNECTION') ?: 'pgsql_slave_test';
        config(['database.default' => $connection]);

        if (empty(config('app.key'))) {
            config(['app.key' => 'base64:' . \base64_encode(\random_bytes(32))]);
        }

        config([
           'database.connections.' . $connection . '.host'     => \getenv('SLAVE_DB_HOST'),
           'database.connections.' . $connection . '.username' => \getenv('SLAVE_DB_USERNAME'),
           'database.connections.' . $connection . '.password' => \getenv('SLAVE_DB_PASSWORD'),
           'database.connections.' . $connection . '.driver'   => \getenv('SLAVE_DB_DRIVER'),
           'database.connections.' . $connection . '.port'     => \getenv('SLAVE_DB_PORT'),
           'database.connections.' . $connection . '.database' => \getenv('SLAVE_DB_DATABASE'),
           // 'app.url'                                            => getenv('SLAVE_APP_URL'),
           'app.name' => \getenv('SLAVE_APP_NAME'),
        ]);
    }

    private function initHost(): void
    {
        $url = \getenv('SLAVE_APP_URL');
        // Sync env
        $_SERVER['APP_URL'] = $url;
    }
}
