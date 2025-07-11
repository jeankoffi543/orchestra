<?php

namespace Kjos\Orchestra\Contexts;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Kjos\Orchestra\Facades\Concerns\CreateSlaveApplication;

abstract class SlaveTestCase extends BaseTestCase
{
    use CreateSlaveApplication;
    use RefreshDatabase;

    /**
     * Perform any work that should take place before the database has started refreshing.
     *
     * @return void
     */
    protected function beforeRefreshingDatabase()
    {
        if (! \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh', [
                '--database' => \getenv('SLAVE_DB_CONNECTION'),
                '--realpath' => true,
                '--path'     => base_path('database/migrations/tenants'),
            ]);
        }
    }
}
