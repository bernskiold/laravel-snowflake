<?php

use Bernskiold\LaravelSnowflake\Tests\Fixtures\BindingCapturingStatement;
use Illuminate\Database\QueryException;

it('formats date bindings using the grammar date format', function () {
    $bindings = $this->makeConnection()->prepareBindings([new DateTime('2026-01-02 03:04:05')]);

    expect($bindings)->toBe(['2026-01-02 03:04:05']);
});

it('passes scalar bindings through untouched', function () {
    $bindings = $this->makeConnection()->prepareBindings(['42', 7, 1.5, true, false, 'text']);

    expect($bindings)->toBe(['42', 7, 1.5, true, false, 'text']);
});

it('never coerces numeric strings', function () {
    $bindings = $this->makeConnection()->prepareBindings(['00123', '0', '0500', '1.5', '99999999999999999999']);

    expect($bindings)->toBe(['00123', '0', '0500', '1.5', '99999999999999999999']);
});

it('binds booleans as TRUE/FALSE strings and detects parameter types', function () {
    $statement = new BindingCapturingStatement;

    $this->makeConnection()->bindValues($statement, [true, false, '00123', 42, '42', 1.5, 'text', null]);

    expect($statement->bound)->toBe([
        1 => ['TRUE', PDO::PARAM_STR],
        2 => ['FALSE', PDO::PARAM_STR],
        3 => ['00123', PDO::PARAM_STR],
        4 => [42, PDO::PARAM_INT],
        5 => ['42', PDO::PARAM_STR],
        6 => [1.5, PDO::PARAM_STR],
        7 => ['text', PDO::PARAM_STR],
        8 => [null, PDO::PARAM_NULL],
    ]);
});

it('keeps named parameters when binding values', function () {
    $statement = new BindingCapturingStatement;

    $this->makeConnection()->bindValues($statement, ['name' => 'John']);

    expect($statement->bound)->toBe(['name' => ['John', PDO::PARAM_STR]]);
});

// Laravel heals a dead connection by matching the driver's message against a
// fixed list of strings, and Snowflake produces none of them — it reports a
// broken session as "Request returned as being unsuccessful". Left alone, the
// dead PDO stays cached and every later query in that process fails instantly.
it('drops a connection the driver reports as broken', function () {
    $connection = $this->makeConnection();

    expect($connection->getPdo())->not->toBeNull();

    $driverError = new PDOException('SQLSTATE[08001]: Client unable to establish connection: 240009 Request returned as being unsuccessful');
    $driverError->errorInfo = ['08001', 240009, 'Request returned as being unsuccessful'];

    $handle = new ReflectionMethod($connection, 'handleQueryException');

    // Still failed, never replayed: retrying here would apply a write twice
    // when its response was lost after it had already run.
    expect(fn () => $handle->invoke(
        $connection,
        new QueryException('snowflake', 'select 1', [], $driverError),
        'select 1',
        [],
        fn () => null,
    ))->toThrow(QueryException::class);

    expect($connection->getPdo())->toBeNull();
});

it('keeps the connection when the statement itself was at fault', function () {
    $connection = $this->makeConnection();

    $driverError = new PDOException('SQLSTATE[42S02]: Base table or view not found');
    $driverError->errorInfo = ['42S02', 2003, 'Object does not exist or not authorized'];

    $handle = new ReflectionMethod($connection, 'handleQueryException');

    expect(fn () => $handle->invoke(
        $connection,
        new QueryException('snowflake', 'select 1', [], $driverError),
        'select 1',
        [],
        fn () => null,
    ))->toThrow(QueryException::class);

    // A missing table says nothing about the session — throwing this one away
    // would mean a fresh login for every typo.
    expect($connection->getPdo())->not->toBeNull();
});
