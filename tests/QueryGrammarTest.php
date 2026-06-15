<?php

use Bernskiold\LaravelSnowflake\SnowflakeConnection;

function connection(array $config = []): SnowflakeConnection
{
    return test()->makeConnection($config);
}

it('uppercases identifiers by default', function () {
    $sql = connection()->query()->from('users')->where('name', 'John')->toSql();

    expect($sql)->toBe('select * from USERS where NAME = ?');
});

it('quotes identifiers when case sensitivity is enabled via the global configuration', function () {
    $this->setColumnsCaseSensitive(true);

    $sql = connection()->query()->from('users')->where('name', 'John')->toSql();

    expect($sql)->toBe('select * from "users" where "name" = ?');
});

it('quotes identifiers when case sensitivity is enabled via the connection options', function () {
    $sql = connection(['options' => ['case_sensitive' => true]])
        ->query()->from('users')->where('name', 'John')->toSql();

    expect($sql)->toBe('select * from "users" where "name" = ?');
});

it('lets the connection option override the global configuration for case sensitivity', function () {
    $this->setColumnsCaseSensitive(true);

    $sql = connection(['options' => ['case_sensitive' => false]])
        ->query()->from('users')->where('name', 'John')->toSql();

    expect($sql)->toBe('select * from USERS where NAME = ?');
});

it('wraps column aliases', function () {
    $sql = connection()->query()->from('users')->select('name as display_name')->toSql();

    expect($sql)->toBe('select NAME as DISPLAY_NAME from USERS');
});

it('wraps table aliases and joins', function () {
    $sql = connection()->query()
        ->from('users as u')
        ->join('orders as o', 'u.id', '=', 'o.user_id')
        ->toSql();

    expect($sql)->toBe('select * from USERS as U inner join ORDERS as O on U.ID = O.USER_ID');
});

it('wraps aliases as quoted identifiers in case-sensitive mode', function () {
    $sql = connection(['options' => ['case_sensitive' => true]])
        ->query()->from('users as u')->select('name as display_name')->toSql();

    expect($sql)->toBe('select "name" as "display_name" from "users" as "u"');
});

it('compiles like to ilike by default', function () {
    $sql = connection()->query()->from('users')->where('name', 'like', '%john%')->toSql();

    expect($sql)->toBe('select * from USERS where NAME ilike ?');
});

it('compiles not like to not ilike by default', function () {
    $sql = connection()->query()->from('users')->where('name', 'not like', '%john%')->toSql();

    expect($sql)->toBe('select * from USERS where NAME not ilike ?');
});

it('can disable ilike per connection', function () {
    $sql = connection(['options' => ['use_ilike' => false]])
        ->query()
        ->from('users')
        ->where('name', 'like', '%john%')
        ->toSql();

    expect($sql)->toBe('select * from USERS where NAME like ?');
});

it('respects the case sensitivity flag of whereLike', function () {
    $insensitive = connection()->query()->from('users')->whereLike('name', '%john%')->toSql();
    $sensitive = connection()->query()->from('users')->whereLike('name', '%john%', true)->toSql();

    expect($insensitive)->toBe('select * from USERS where NAME ilike ?')
        ->and($sensitive)->toBe('select * from USERS where NAME like ?');
});

it('uses a native date cast for whereDate', function () {
    $sql = connection()->query()->from('orders')->whereDate('created_at', '2026-01-01')->toSql();

    expect($sql)->toBe('select * from ORDERS where CREATED_AT = ?::DATE');
});

it('uses TO_VARCHAR for whereYear', function () {
    $sql = connection()->query()->from('orders')->whereYear('created_at', 2026)->toSql();

    expect($sql)->toBe("select * from ORDERS where TO_VARCHAR(CREATED_AT, 'YYYY') = ?");
});

it('compiles truncate to a single truncate table statement', function () {
    $connection = connection();
    $query = $connection->query()->from('users');

    expect($connection->getQueryGrammar()->compileTruncate($query))->toBe(['truncate table USERS' => []]);
});

it('aliases aggregates as aggregate', function () {
    $query = connection()->query()->from('users');
    $query->aggregate = ['function' => 'count', 'columns' => ['*']];

    expect($query->toSql())->toBe('select count(*) as "aggregate" from USERS');
});

it('compiles exists without using EXISTS in the select list', function () {
    $connection = connection();
    $query = $connection->query()->from('users')->where('active', true);

    expect($connection->getQueryGrammar()->compileExists($query))->toBe(
        'select count(*) as "exists" from (select * from USERS where ACTIVE = ?) as laravel_exists'
    );
});

it('compiles locks to nothing as Snowflake does not support them', function () {
    $sql = connection()->query()->from('users')->lockForUpdate()->toSql();

    expect($sql)->toBe('select * from USERS');
});

it('compiles limit and offset', function () {
    $sql = connection()->query()->from('users')->limit(10)->offset(5)->toSql();

    expect($sql)->toBe('select * from USERS limit 10 offset 5');
});

it('does not support savepoints', function () {
    expect(connection()->getQueryGrammar()->supportsSavepoints())->toBeFalse();
});

it('compiles upsert into a merge statement', function () {
    $connection = connection();
    $query = $connection->query()->from('users');

    $sql = $connection->getQueryGrammar()->compileUpsert(
        $query,
        [['email' => 'jane@example.com', 'name' => 'Jane']],
        ['email'],
        ['name']
    );

    expect($sql)->toBe(
        'merge into USERS using (select column1 as EMAIL, column2 as NAME from values (?, ?)) as laravel_source '
        .'on USERS.EMAIL = laravel_source.EMAIL '
        .'when matched then update set NAME = laravel_source.NAME '
        .'when not matched then insert (EMAIL, NAME) values (laravel_source.EMAIL, laravel_source.NAME)'
    );
});

it('compiles upsert value overrides as parameters', function () {
    $connection = connection();
    $query = $connection->query()->from('users');

    $sql = $connection->getQueryGrammar()->compileUpsert(
        $query,
        [['email' => 'jane@example.com', 'name' => 'Jane']],
        ['email'],
        ['name' => 'Updated']
    );

    expect($sql)->toContain('when matched then update set NAME = ?');
});

it('rejects insert or ignore', function () {
    $connection = connection();

    $connection->getQueryGrammar()->compileInsertOrIgnore($connection->query()->from('users'), [['id' => 1]]);
})->throws(RuntimeException::class, 'does not support insert or ignore');

it('rejects updates with a limit', function () {
    $connection = connection();
    $query = $connection->query()->from('users')->limit(5);

    $connection->getQueryGrammar()->compileUpdate($query, ['name' => 'x']);
})->throws(RuntimeException::class, 'update statements with a limit');

it('rejects updates with joins', function () {
    $connection = connection();
    $query = $connection->query()->from('users')->join('orders', 'users.id', '=', 'orders.user_id');

    $connection->getQueryGrammar()->compileUpdate($query, ['name' => 'x']);
})->throws(RuntimeException::class, 'update statements with joins');

it('rejects deletes with a limit', function () {
    $connection = connection();
    $query = $connection->query()->from('users')->limit(5);

    $connection->getQueryGrammar()->compileDelete($query);
})->throws(RuntimeException::class, 'delete statements with a limit');

it('rejects deletes with joins', function () {
    $connection = connection();
    $query = $connection->query()->from('users')->join('orders', 'users.id', '=', 'orders.user_id');

    $connection->getQueryGrammar()->compileDelete($query);
})->throws(RuntimeException::class, 'delete statements with joins');

it('rejects updates of individual json keys', function () {
    $connection = connection();

    $connection->getQueryGrammar()->compileUpdate(
        $connection->query()->from('users'),
        ['settings->theme' => 'dark']
    );
})->throws(RuntimeException::class, 'updating individual JSON keys');

it('compiles json selectors with get_path', function () {
    $sql = connection()->query()->from('users')->where('settings->theme', 'dark')->toSql();

    expect($sql)->toBe("select * from USERS where get_path(SETTINGS, 'theme') = ?");
});

it('compiles nested json selectors', function () {
    $sql = connection()->query()->from('users')->where('settings->theme->color', 'blue')->toSql();

    expect($sql)->toBe("select * from USERS where get_path(SETTINGS, 'theme.color') = ?");
});

it('compiles whereJsonContains using array_contains', function () {
    $query = connection()->query()->from('users')->whereJsonContains('tags', 'admin');

    expect($query->toSql())->toBe('select * from USERS where array_contains(parse_json(?), TAGS)')
        ->and($query->getBindings())->toBe(['"admin"']);
});

it('compiles whereJsonContains on a json path', function () {
    $sql = connection()->query()->from('users')->whereJsonContains('settings->roles', 'admin')->toSql();

    expect($sql)->toBe("select * from USERS where array_contains(parse_json(?), get_path(SETTINGS, 'roles'))");
});

it('compiles whereJsonContainsKey as a null check', function () {
    $sql = connection()->query()->from('users')->whereJsonContainsKey('settings->theme')->toSql();

    expect($sql)->toBe("select * from USERS where get_path(SETTINGS, 'theme') is not null");
});

it('compiles whereJsonLength using array_size', function () {
    $query = connection()->query()->from('users')->whereJsonLength('tags', '>', 2);

    expect($query->toSql())->toBe('select * from USERS where array_size(TAGS) > ?')
        ->and($query->getBindings())->toBe([2]);
});

it('escapes values as Snowflake literals', function () {
    $grammar = connection()->getQueryGrammar();

    expect($grammar->escape(null))->toBe('null')
        ->and($grammar->escape(true))->toBe('true')
        ->and($grammar->escape(false))->toBe('false')
        ->and($grammar->escape(42))->toBe('42')
        ->and($grammar->escape(1.5))->toBe('1.5')
        ->and($grammar->escape("O'Reilly"))->toBe("'O''Reilly'")
        ->and($grammar->escape('a\\b'))->toBe("'a\\\\b'")
        ->and($grammar->escape("\x00\x01", true))->toBe("to_binary('0001', 'hex')");
});

it('compiles whereFullText using the SEARCH function', function () {
    $query = connection()->query()->from('articles')->whereFullText('body', 'snowflake');

    expect($query->toSql())->toBe('select * from ARTICLES where search(BODY, ?)')
        ->and($query->getBindings())->toBe(['snowflake']);
});

it('compiles whereFullText over multiple columns', function () {
    $sql = connection()->query()
        ->from('articles')
        ->whereFullText(['title', 'body'], 'snowflake')
        ->toSql();

    expect($sql)->toBe('select * from ARTICLES where search((TITLE, BODY), ?)');
});

it('compiles whereFullText with a custom analyzer', function () {
    $sql = connection()->query()
        ->from('articles')
        ->whereFullText('body', 'snowflake', ['analyzer' => 'UNICODE_ANALYZER'])
        ->toSql();

    expect($sql)->toBe("select * from ARTICLES where search(BODY, ?, analyzer => 'UNICODE_ANALYZER')");
});
