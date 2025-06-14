<?php

namespace Kjos\Orchestra\Tests;

use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    //
    protected function setUp(): void
    {
        parent::setUp();
        // checking if public/storage/tenants folder exists
        if (!File::exists(base_path('public/storage/tenants'))) {
            File::makeDirectory(base_path('public/storage/tenants'), recursive: true);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // removing public/storage/tenants and site folders
        File::exists(base_path('public/storage/tenants')) && File::deleteDirectory(base_path('public/storage/tenants'));
        File::exists(base_path('site'))                   && File::deleteDirectory(base_path('site'));
    }

    protected function getPackageProviders($app)
    {
        return [
            \Kjos\Orchestra\OrchestraServiceProvider::class,
        ];
    }
}
