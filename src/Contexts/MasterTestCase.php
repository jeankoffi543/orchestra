<?php

namespace Kjos\Orchestra\Contexts;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Kjos\Orchestra\Facades\Concerns\CreateMasterApplication;

abstract class MasterTestCase extends BaseTestCase
{
    use CreateMasterApplication;
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
                '--database' => \getenv('MASTER_DB_CONNECTION'),
                '--realpath' => true,
                '--path'     => base_path('database/migrations'),
            ]);
        }
    }
}
