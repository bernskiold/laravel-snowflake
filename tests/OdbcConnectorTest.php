<?php

use Bernskiold\LaravelSnowflake\Odbc\OdbcConnector;

function buildDsn(array $config): string
{
    $connector = new OdbcConnector;
    $method = new ReflectionMethod($connector, 'buildDsnDynamicly');

    return $method->invoke($connector, $config);
}

it('builds a DSN from the connection configuration', function () {
    $dsn = buildDsn([
        'driver' => 'odbc',
        'odbc_driver' => '/opt/snowflake/libSnowflake.so',
        'server' => 'account.snowflakecomputing.com',
        'database' => 'TESTDB',
        'warehouse' => 'COMPUTE_WH',
        'username' => 'user',
        'password' => 'secret',
    ]);

    expect($dsn)->toBe('odbc:driver=/opt/snowflake/libSnowflake.so;server=account.snowflakecomputing.com;database=TESTDB;warehouse=COMPUTE_WH');
});

it('excludes credentials and reserved keys from the DSN', function () {
    $dsn = buildDsn([
        'driver' => 'odbc',
        'odbc_driver' => 'Snowflake',
        'name' => 'snowflake',
        'prefix' => '',
        'options' => [],
        'username' => 'user',
        'password' => 'secret',
        'schema' => 'PUBLIC',
    ]);

    expect($dsn)->toBe('odbc:driver=Snowflake;schema=PUBLIC')
        ->and($dsn)->not->toContain('secret');
});

it('requires the odbc_driver option when building dynamically', function () {
    buildDsn([
        'driver' => 'odbc',
        'server' => 'account.snowflakecomputing.com',
    ]);
})->throws(Exception::class, 'DB_ODBC_DRIVER');
