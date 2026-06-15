<?php

use Bernskiold\LaravelSnowflake\SnowflakeProcessor;

it('selects the max id after an insert using the right casing', function () {
    $connection = $this->makeConnection();
    $connection->getPdo()->exec('CREATE TABLE USERS (ID INTEGER, NAME TEXT)');

    $id = $connection->table('users')->insertGetId(['id' => 7, 'name' => 'Jane']);

    expect($id)->toBe(7);
});

it('supports a custom sequence column for insertGetId', function () {
    $connection = $this->makeConnection();
    $connection->getPdo()->exec('CREATE TABLE ITEMS (ITEM_ID INTEGER)');

    $id = $connection->table('items')->insertGetId(['item_id' => 3], 'item_id');

    expect($id)->toBe(3);
});

it('processes information_schema columns into the Laravel shape', function () {
    $processor = new SnowflakeProcessor;

    $columns = $processor->processColumns([
        (object) [
            'name' => 'ID',
            'type_name' => 'NUMBER',
            'type' => 'NUMBER',
            'char_length' => null,
            'numeric_precision' => 38,
            'numeric_scale' => 0,
            'nullable' => 'NO',
            'default' => null,
            'auto_increment' => 'YES',
            'collation' => null,
            'comment' => null,
        ],
        (object) [
            'name' => 'NAME',
            'type_name' => 'TEXT',
            'type' => 'TEXT',
            'char_length' => 255,
            'numeric_precision' => null,
            'numeric_scale' => null,
            'nullable' => 'YES',
            'default' => "'guest'",
            'auto_increment' => 'NO',
            'collation' => null,
            'comment' => 'Display name',
        ],
    ]);

    expect($columns)->toBe([
        [
            'name' => 'ID',
            'type_name' => 'number',
            'type' => 'number(38,0)',
            'collation' => null,
            'nullable' => false,
            'default' => null,
            'auto_increment' => true,
            'comment' => null,
            'generation' => null,
        ],
        [
            'name' => 'NAME',
            'type_name' => 'text',
            'type' => 'text(255)',
            'collation' => null,
            'nullable' => true,
            'default' => "'guest'",
            'auto_increment' => false,
            'comment' => 'Display name',
            'generation' => null,
        ],
    ]);
});
