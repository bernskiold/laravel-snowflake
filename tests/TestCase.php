<?php

namespace Bernskiold\LaravelSnowflake\Tests;

use Bernskiold\LaravelSnowflake\SnowflakeConnection;
use Bernskiold\LaravelSnowflake\SnowflakeServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use PDO;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            SnowflakeServiceProvider::class,
        ];
    }

    /**
     * Create a Snowflake connection backed by an in-memory SQLite PDO,
     * which is enough to exercise grammars, processors and bindings
     * without a real Snowflake server.
     */
    protected function makeConnection(array $config = []): SnowflakeConnection
    {
        $pdo = new PDO('sqlite::memory:');

        return new SnowflakeConnection($pdo, 'TESTDB', '', array_merge([
            'name' => 'snowflake',
            'driver' => 'snowflake',
        ], $config));
    }

    /**
     * Toggle the global snowflake.case_sensitive configuration value.
     */
    protected function setColumnsCaseSensitive(bool $value): void
    {
        config()->set('snowflake.case_sensitive', $value);
    }
}
