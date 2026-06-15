<?php

use Bernskiold\LaravelSnowflake\Grammars\QueryGrammar;
use Bernskiold\LaravelSnowflake\Grammars\SchemaGrammar;
use Bernskiold\LaravelSnowflake\Schema\Builder as SchemaBuilder;
use Bernskiold\LaravelSnowflake\SnowflakeProcessor;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Support\Facades\DB;

it('registers the snowflake driver', function () {
    expect(Connection::getResolver('snowflake'))->toBeInstanceOf(Closure::class);
});

it('registers the native snowflake driver', function () {
    expect(Connection::getResolver('snowflake_native'))->toBeInstanceOf(Closure::class);
});

it('registers the generic odbc driver', function () {
    expect(Connection::getResolver('odbc'))->toBeInstanceOf(Closure::class);
});

it('requires the pdo_snowflake extension for native connections', function () {
    config()->set('database.connections.snowflake', [
        'driver' => 'snowflake_native',
        'account' => 'test-account',
        'username' => 'user',
        'password' => 'secret',
        'database' => 'TESTDB',
    ]);

    DB::connection('snowflake')->getPdo();
})->throws(Exception::class, 'Native Snowflake driver pdo_snowflake was not enabled')
    ->skip(extension_loaded('pdo_snowflake'), 'pdo_snowflake is loaded in this environment.');

it('uses the snowflake grammars, processor and schema builder', function () {
    $connection = $this->makeConnection();

    expect($connection->getQueryGrammar())->toBeInstanceOf(QueryGrammar::class)
        ->and($connection->getDefaultSchemaGrammar())->toBeInstanceOf(SchemaGrammar::class)
        ->and($connection->getPostProcessor())->toBeInstanceOf(SnowflakeProcessor::class)
        ->and($connection->getSchemaBuilder())->toBeInstanceOf(SchemaBuilder::class);
});

it('allows custom grammars and processor via the connection options', function () {
    $connection = $this->makeConnection([
        'options' => [
            'processor' => Processor::class,
            'grammar' => [
                'query' => Grammar::class,
                'schema' => Illuminate\Database\Schema\Grammars\Grammar::class,
            ],
        ],
    ]);

    expect(get_class($connection->getQueryGrammar()))->toBe(Grammar::class)
        ->and(get_class($connection->getPostProcessor()))->toBe(Processor::class);
});
