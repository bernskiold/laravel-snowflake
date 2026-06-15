<?php

use Bernskiold\LaravelSnowflake\Tests\Fixtures\BindingCapturingStatement;

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
