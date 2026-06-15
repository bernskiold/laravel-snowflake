<?php

use Bernskiold\LaravelSnowflake\PDO\Statement;

function statementPdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [Statement::class, [$pdo]]);

    return $pdo;
}

it('interpolates bound values into the query', function () {
    $pdo = statementPdo();

    $statement = $pdo->prepare('select ? as value');
    $statement->bindValue(1, 'hello');
    $statement->execute();

    expect($statement->fetchColumn())->toBe('hello');
});

it('escapes quotes in interpolated strings', function () {
    $pdo = statementPdo();

    $statement = $pdo->prepare('select ? as value');
    $statement->bindValue(1, "O'Reilly");
    $statement->execute();

    expect($statement->fetchColumn())->toBe("O'Reilly");
});

it('casts integer bindings', function () {
    $pdo = statementPdo();

    $statement = $pdo->prepare('select ? as value');
    $statement->bindValue(1, '42', PDO::PARAM_INT);
    $statement->execute();

    expect($statement->fetchColumn())->toBe(42);
});

it('interpolates null bindings as null literals', function () {
    $pdo = statementPdo();

    $statement = $pdo->prepare('select ? as value');
    $statement->bindValue(1, null);
    $statement->execute();

    expect($statement->fetchColumn())->toBeNull();
});

it('interpolates boolean bindings as TRUE and FALSE literals', function () {
    $pdo = statementPdo();

    $statement = $pdo->prepare('select ? as value');
    $statement->bindValue(1, true, PDO::PARAM_BOOL);
    $statement->execute();

    expect($statement->fetchColumn())->toBe(1);
});

it('accepts parameters passed directly to execute', function () {
    $pdo = statementPdo();

    $statement = $pdo->prepare('select ? as value');
    $statement->execute(['direct']);

    expect($statement->fetchColumn())->toBe('direct');
});

it('renders Snowflake string literals with escaped backslashes', function () {
    $pdo = statementPdo();

    $statement = $pdo->prepare('select ? as value');
    $statement->bindValue(1, 'a\\b');

    $method = new ReflectionMethod($statement, '_prepareValues');

    expect($method->invoke($statement))->toBe([1 => "'a\\\\b'"]);
});

it('executes DDL statements directly and reports success', function () {
    $pdo = statementPdo();

    $statement = $pdo->prepare('CREATE TABLE demo (id int)');

    expect($statement->execute())->toBeTrue();

    $check = $pdo->prepare("select name from sqlite_master where type = 'table' and name = 'demo'");
    $check->execute();

    expect($check->fetchColumn())->toBe('demo');
});

it('reports affected rows through rowCount', function () {
    $pdo = statementPdo();

    $pdo->prepare('CREATE TABLE demo (id int)')->execute();

    $insert = $pdo->prepare('insert into demo (id) values (?)');
    $insert->bindValue(1, 1, PDO::PARAM_INT);
    $insert->execute();

    expect($insert->rowCount())->toBe(1);
});
