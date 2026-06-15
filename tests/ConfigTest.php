<?php

use Bernskiold\LaravelSnowflake\SnowflakeServiceProvider;
use Illuminate\Support\ServiceProvider;

it('merges the package configuration with safe defaults', function () {
    expect(config('snowflake.case_sensitive'))->toBeFalse()
        ->and(config('snowflake.use_ilike'))->toBeTrue()
        ->and(config('snowflake.force_quoted_identifiers'))->toBeTrue();
});

it('publishes the configuration file', function () {
    $paths = ServiceProvider::pathsToPublish(SnowflakeServiceProvider::class, 'snowflake-config');

    expect($paths)->toHaveCount(1)
        ->and(array_key_first($paths))->toEndWith('config/snowflake.php')
        ->and(reset($paths))->toEndWith('config/snowflake.php');
});

it('reads case sensitivity from the package configuration', function () {
    config()->set('snowflake.case_sensitive', true);

    $sql = $this->makeConnection()->query()->from('users')->where('name', 'John')->toSql();

    expect($sql)->toBe('select * from "users" where "name" = ?');
});

it('reads the ilike behaviour from the package configuration', function () {
    config()->set('snowflake.use_ilike', false);

    $sql = $this->makeConnection()->query()->from('users')->where('name', 'like', '%j%')->toSql();

    expect($sql)->toBe('select * from USERS where NAME like ?');
});

it('prefers per-connection options over the package configuration', function () {
    config()->set('snowflake.case_sensitive', true);
    config()->set('snowflake.use_ilike', false);

    $connection = $this->makeConnection([
        'options' => ['case_sensitive' => false, 'use_ilike' => true],
    ]);

    $sql = $connection->query()->from('users')->where('name', 'like', '%j%')->toSql();

    expect($sql)->toBe('select * from USERS where NAME ilike ?');
});
