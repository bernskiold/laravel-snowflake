# Laravel Snowflake

[![Tests](https://github.com/bernskiold/laravel-pdo-odbc/actions/workflows/tests.yml/badge.svg)](https://github.com/bernskiold/laravel-pdo-odbc/actions/workflows/tests.yml)

A Snowflake database driver for Laravel, with full support for the query
builder, Eloquent and schema migrations. It connects to Snowflake either
through the native [`pdo_snowflake`](https://github.com/snowflakedb/pdo_snowflake)
PHP extension (recommended) or through ODBC, and also works as a generic
PDO/ODBC driver for other ODBC data sources.

## Requirements

- PHP 8.2+
- Laravel 12+
- The [`pdo_snowflake`](https://github.com/snowflakedb/pdo_snowflake) extension
  (for native connections) **or** the
  [Snowflake ODBC driver](https://docs.snowflake.com/en/developer-guide/odbc/odbc)
  together with PHP's `odbc` and `pdo_odbc` extensions

## Installation

```bash
composer require bernskiold/laravel-snowflake
```

The service provider is registered automatically through package discovery.
If you have disabled discovery, register it manually:

```php
'providers' => [
    // ...
    Bernskiold\LaravelSnowflake\SnowflakeServiceProvider::class,
],
```

Optionally publish the configuration file to change the package defaults:

```bash
php artisan vendor:publish --tag=snowflake-config
```

## Configuration

Add a connection to `config/database.php`. The package registers three
drivers:

| Driver | Description |
| --- | --- |
| `snowflake_native` | Snowflake through the `pdo_snowflake` extension (recommended) |
| `snowflake` | Snowflake through the Snowflake ODBC driver |
| `odbc` | Generic ODBC connection for any other data source |

### Native Snowflake (recommended)

```php
'snowflake' => [
    'driver' => 'snowflake_native',
    'account' => '{account_name}.eu-west-1',
    'username' => env('SNOWFLAKE_USERNAME'),
    'password' => env('SNOWFLAKE_PASSWORD'),
    'database' => env('SNOWFLAKE_DATABASE'),
    'warehouse' => env('SNOWFLAKE_WAREHOUSE'),
    'schema' => 'PUBLIC',
],
```

### Snowflake via ODBC

```php
'snowflake' => [
    'driver' => 'snowflake',
    // Absolute path to the driver file, or the name registered in odbcinst.ini.
    'odbc_driver' => '/opt/snowflake/snowflakeodbc/lib/universal/libSnowflake.dylib',
    'server' => '{account_name}.snowflakecomputing.com',
    'username' => env('SNOWFLAKE_USERNAME'),
    'password' => env('SNOWFLAKE_PASSWORD'),
    'database' => env('SNOWFLAKE_DATABASE'),
    'warehouse' => env('SNOWFLAKE_WAREHOUSE'),
    'schema' => 'PUBLIC',
    'options' => [
        // Required for Snowflake ODBC usage.
        \PDO::ODBC_ATTR_USE_CURSOR_LIBRARY => \PDO::ODBC_SQL_USE_DRIVER,
    ],
],
```

All configuration fields except `driver`, `odbc_driver`, `options`,
`username`, `password`, `name` and `prefix` are appended to the DSN
connection string, so any extra Snowflake connection parameter can simply be
added to the connection configuration.

### Key-pair authentication

Both drivers support [key-pair authentication](https://docs.snowflake.com/en/user-guide/key-pair-auth).
Set the `authenticator` to `key_pair` and provide the private key either as a
path or inline:

```php
'snowflake' => [
    'driver' => 'snowflake_native',
    'account' => '{account_name}.eu-west-1',
    'username' => env('SNOWFLAKE_USERNAME'),
    'authenticator' => 'key_pair',

    // Either a path to a PEM-encoded private key...
    'private_key_path' => env('SNOWFLAKE_PRIVATE_KEY_PATH'),

    // ...or the key itself. It is written to a temporary file (0600) that is
    // removed as soon as the connection has been established.
    'private_key' => env('SNOWFLAKE_PRIVATE_KEY'),

    // Optional, when the private key is encrypted.
    'private_key_passphrase' => env('SNOWFLAKE_PRIVATE_KEY_PASSPHRASE'),

    'database' => env('SNOWFLAKE_DATABASE'),
    'warehouse' => env('SNOWFLAKE_WAREHOUSE'),
    'schema' => 'PUBLIC',
],
```

### Generic ODBC connections

```php
'odbc-connection-name' => [
    'driver' => 'odbc',
    // Either reference a DSN configured in your ODBC manager...
    'dsn' => 'OdbcConnectionName',
    // ...or provide a full connection string:
    // 'dsn' => 'Driver={Driver Name};Server=server.example.com;Port=443;Database={DatabaseName}',
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
],
```

All other connection parameters (for example `role`) are appended to the DSN
automatically, so any Snowflake connection parameter can be set directly in
the connection configuration.

## Options

All package behaviour can be configured globally through
`config/snowflake.php` (publish it with
`php artisan vendor:publish --tag=snowflake-config`) and overridden per
connection through the connection's `options` array. Environment variables
are only read inside the config file, so values keep working when the
configuration is cached with `php artisan config:cache`.

| Option | Default | Description |
| --- | --- | --- |
| `case_sensitive` | `false` | Quote identifiers and keep their casing instead of uppercasing them |
| `use_ilike` | `true` | Compile `LIKE` to the case-insensitive `ILIKE` |
| `force_quoted_identifiers` | `true` | Run `ALTER SESSION SET QUOTED_IDENTIFIERS_IGNORE_CASE = false` on connect |

### Column case sensitivity

Snowflake folds unquoted identifiers to uppercase. By default this package
follows that convention and uppercases all column and table names, leaving
them unquoted. To use quoted, case-sensitive identifiers instead, enable
`case_sensitive` in `config/snowflake.php` (or the
`SNOWFLAKE_COLUMNS_CASE_SENSITIVE` environment variable), or enable it for a
single connection:

```php
'snowflake' => [
    // ...
    'options' => [
        'case_sensitive' => true,
    ],
],
```

### Case-insensitive LIKE

The query grammar compiles `LIKE` clauses to `ILIKE` (case-insensitive) by
default, which matches typical Laravel expectations. Disable it globally via
`use_ilike` in `config/snowflake.php`, or per connection if you want
Snowflake's case-sensitive `LIKE` behaviour:

```php
'snowflake' => [
    // ...
    'options' => [
        'use_ilike' => false,
    ],
],
```

### Quoted identifier session behaviour

On connect, the package executes
`ALTER SESSION SET QUOTED_IDENTIFIERS_IGNORE_CASE = false` so quoted
identifiers keep their case. Disable it via `force_quoted_identifiers` in
`config/snowflake.php` or the same key in the connection's `options` array.

## Usage

Use the query builder and Eloquent as you would with any other connection:

```php
use Illuminate\Support\Facades\DB;

$books = DB::connection('snowflake')
    ->table('books')
    ->where('author', 'Abram Andrea')
    ->get();

$books = Book::where('author', 'Abram Andrea')->get();
```

### Upserts

`upsert()` (and `updateOrCreate()`) compile to a native Snowflake
`MERGE INTO` statement:

```php
DB::connection('snowflake')->table('flights')->upsert(
    [['departure' => 'Oakland', 'destination' => 'San Diego', 'price' => 99]],
    ['departure', 'destination'],
    ['price']
);
```

### JSON / VARIANT columns

`$table->json()` columns are created as Snowflake `VARIANT` columns, and the
query builder's JSON helpers compile to Snowflake's semi-structured
functions:

```php
// get_path(SETTINGS, 'theme') = ?
User::where('settings->theme', 'dark')->get();

// array_contains(parse_json(?), TAGS)
User::whereJsonContains('tags', 'admin')->get();

// array_size(TAGS) > ?
User::whereJsonLength('tags', '>', 2)->get();
```

#### Writing VARIANT data

Snowflake will not implicitly coerce a string literal into a `VARIANT`,
`OBJECT` or `ARRAY` column — the value has to be parsed with `PARSE_JSON()`.
Wrap semi-structured values with `Snowflake::variant()` and the query grammar
emits `PARSE_JSON(?)` for them on insert, update and upsert, while still
binding the JSON payload as a normal parameter:

```php
use Bernskiold\LaravelSnowflake\Snowflake;

DB::connection('snowflake')->table('countries')->insert([
    'code' => 'DE',
    // insert into COUNTRIES (CODE, NAME) values (?, PARSE_JSON(?))
    'name' => Snowflake::variant(['en' => 'Germany', 'de' => 'Deutschland']),
]);
```

`Snowflake::variant()` accepts arrays (encoded for you), already-encoded JSON
strings (passed through), and `null` (`PARSE_JSON(NULL)` is SQL `NULL`, so
nullable columns are safe).

On Eloquent models, the `AsVariant` cast does this automatically and decodes
the value back to a PHP array on read:

```php
use Bernskiold\LaravelSnowflake\Casts\AsVariant;

protected function casts(): array
{
    return [
        'name' => AsVariant::class,
        'languages' => AsVariant::class,
    ];
}
```

Wrapping a value in a `Variant` only changes the placeholder it compiles to
(`PARSE_JSON(?)` instead of `?`) — the JSON payload is still sent as a bound
parameter, never interpolated into the SQL string. The same handling applies
across every write path, so variant and scalar columns can be mixed freely:

- `insert()` and `insertGetId()`
- `update()`
- `upsert()` (compiled as a Snowflake `MERGE`), including values used in the
  matched `update set` clause

**Reading back.** Snowflake returns a `VARIANT` column as a JSON string. The
`AsVariant` cast decodes it to a PHP value for you; without the cast you
receive the raw JSON string and can decode it yourself. To query *into* a
variant column, use the JSON operators shown above
(`where('settings->theme', …)`, `whereJsonContains()`, …).

**Schema.** Migrations create the matching column type automatically:
`$table->json()` and `$table->jsonb()` both compile to a Snowflake `VARIANT`.

> **Verification note:** VARIANT handling is covered by the test suite at the
> SQL-generation level (the compiled SQL contains `PARSE_JSON(?)` and bindings
> stay aligned). The automated tests run against SQLite, which has no
> `PARSE_JSON`, so a full round-trip is best confirmed against a real Snowflake
> connection in your own integration tests.

### Full-text search

`whereFullText()` compiles to Snowflake's
[`SEARCH()`](https://docs.snowflake.com/en/sql-reference/functions/search)
function:

```php
// search(BODY, ?)
Article::whereFullText('body', 'snowflake')->get();

// search((TITLE, BODY), ?)
Article::whereFullText(['title', 'body'], 'snowflake')->get();

// search(BODY, ?, analyzer => 'UNICODE_ANALYZER')
Article::whereFullText('body', 'snowflake', ['analyzer' => 'UNICODE_ANALYZER'])->get();
```

### Time travel

Two query builder macros expose [Snowflake time travel](https://docs.snowflake.com/en/sql-reference/constructs/at-before):

```php
// select * from ORDERS at (timestamp => '...'::timestamp_tz)
DB::table('orders')->atTimestamp(now()->subHour())->get();

// select * from ORDERS before (statement => '...')
DB::table('orders')->beforeStatement('8e5d0ca9-005e-44e6-b858-a8f5b37c5726')->get();
```

Call the macro after `from()` / `table()` — it wraps the current table
expression.

### Schema inspection

`Schema::getTables()`, `Schema::getColumns()`, `Schema::hasTable()`,
`Schema::hasColumn()` and the `db:show` / `db:table` / `model:show` artisan
commands work against `information_schema`. Since Snowflake has no indexes,
index listings are always empty and `$table->index()` calls in migrations
are silently skipped.

### Known limitations

These operations have no Snowflake equivalent and throw a `RuntimeException`
with a descriptive message instead of emitting invalid SQL:

- `insertOrIgnore()` — use `upsert()` instead
- `update()` / `delete()` combined with `join()` or `limit()` — use a raw
  `MERGE` statement instead
- Updating individual JSON keys (`->update(['settings->theme' => ...])`) —
  update the whole column instead

Additionally: foreign key and unique constraints are created but Snowflake
does not enforce them; `enum` columns are stored as plain `varchar`; locks
(`lockForUpdate()`, `sharedLock()`) compile to nothing; and savepoints
(nested transactions) are not supported.

### A note on `insertGetId()`

Snowflake's PDO drivers do not support `lastInsertId`, so `insertGetId()`
(used by `Model::create()` on auto-increment keys) selects `max()` of the
key column after the insert. **This is inherently racy**: when several
processes insert into the same table concurrently, the returned id can
belong to another session's row. For tables written to by queue workers or
concurrent requests, prefer client-generated keys instead:

```php
// In your migration
$table->uuid('id')->primary();

// In your model
use Illuminate\Database\Eloquent\Concerns\HasUuids;
```

## Operational notes

A few Snowflake characteristics worth knowing when running Laravel against
it:

- **DDL is never transactional.** Every DDL statement commits implicitly, so
  a migration that fails halfway leaves the schema partially changed —
  Laravel cannot roll it back. Keep migrations small and idempotent where
  possible.
- **Connections are slow to establish** (often 1–2 seconds, since they
  negotiate a cloud session). Long-running processes such as queue workers
  and Laravel Octane amortize this nicely; short-lived processes that open a
  fresh connection per request will feel it.
- **Queries cost money, not just time.** Every query keeps the warehouse
  resumed, so N+1 query patterns hurt the bill as well as latency. Eager
  load relations and batch your writes (`insert()` with many rows compiles
  to one multi-row statement).
- **Snowflake is an analytical database.** Single-row lookups and writes are
  much slower than on OLTP databases — that's by design. Use it for what it
  is good at: large scans, aggregations and reporting.

## Testing

The package ships with a [Pest](https://pestphp.com) test suite, plus
[Pint](https://laravel.com/docs/pint) for code style and
[PHPStan](https://phpstan.org) for static analysis:

```bash
composer test
composer lint
composer analyse
```

## Further documentation

- [Snowflake ODBC notes](docs/snowflake-odbc.md)
- [Snowflake ODBC troubleshooting](docs/snowflake-odbc-troubleshooting.md)
- [Custom `getLastInsertId()` behaviour](docs/custom-last-insert-id.md)
- [Custom processor and grammars](docs/custom-grammars.md)

## Credits & Thanks

This package started as a fork of
[`yoramdelangen/laravel-pdo-odbc`](https://github.com/yoramdelangen/laravel-pdo-odbc)
by Yoram de Langen. Many thanks to Yoram and all of the original
contributors for laying the groundwork for Snowflake and ODBC support in
Laravel.

## License

[MIT](LICENSE.md)
