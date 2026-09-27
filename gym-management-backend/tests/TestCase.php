<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    // Mockery isn't installed; artisan calls (e.g. RefreshDatabase's
    // migrate:fresh) would otherwise try to mock console output with it.
    public $mockConsoleOutput = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests that refresh the database must never touch real data — e.g.
        // if config was cached with the real DB name, phpunit.xml's
        // DB_DATABASE would be ignored.
        $database = config('database.connections.'.config('database.default').'.database');
        if ($database !== 'asian_gym_test') {
            throw new RuntimeException("Refusing to run tests against \"{$database}\". Run `php artisan config:clear` first.");
        }
    }
}
