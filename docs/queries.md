# Queries and Eloquent

This page tells you how the query builder and Eloquent work on Snowflake.

Most query builder methods and Eloquent methods work without changes. This
page tells you about the methods that work differently, and about the
methods that are special to Snowflake.

For the methods that Snowflake does not support, refer to
[Limitations](limitations.md).

## Basic queries

Use a Snowflake connection as you use other connections:

```php
use Illuminate\Support\Facades\DB;

$orders = DB::connection('snowflake')
    ->table('orders')
    ->where('status', 'shipped')
    ->orderByDesc('created_at')
    ->limit(100)
    ->get();
```

Eloquent models work when you set the connection on the model and set the
`lowercase_result_keys` option on the connection. Refer to
[Configuration](configuration.md#case-of-the-column-names-in-results).

```php
class Order extends Model
{
    protected $connection = 'snowflake';
}

$orders = Order::where('status', 'shipped')->get();
```

## Bound values

The query builder uses bound parameters for all values. The native driver
sends them to Snowflake as bound parameters. The ODBC driver puts escaped
values into the SQL text. Refer to [ODBC connections](odbc.md#bound-values).

These rules apply:

- Booleans go to Snowflake as `TRUE` and `FALSE`.
- Dates go to Snowflake in the date format of the grammar.
- Strings stay as strings. The package does not change numeric strings.
  Thus, `'007'` and `'1.50'` keep their leading and trailing zeros.

## Dates and times

The date methods compile to Snowflake functions:

| Method | Compiled SQL |
| --- | --- |
| `whereDate('created_at', '2026-01-01')` | `CREATED_AT = ?::DATE` |
| `whereYear('created_at', 2026)` | `TO_VARCHAR(CREATED_AT, 'YYYY') = ?` |
| `whereMonth()`, `whereDay()`, `whereTime()` | `TO_VARCHAR()` with the applicable format |

## Upserts

The `upsert()` method compiles to a Snowflake `MERGE INTO` statement:

```php
DB::connection('snowflake')->table('users')->upsert(
    [
        ['email' => 'ada@example.com', 'name' => 'Ada'],
        ['email' => 'alan@example.com', 'name' => 'Alan'],
    ],
    uniqueBy: ['email'],
    update: ['name'],
);
```

```sql
merge into USERS
using (select column1 as EMAIL, column2 as NAME from values (?, ?), (?, ?)) as laravel_source
on USERS.EMAIL = laravel_source.EMAIL
when matched then update set NAME = laravel_source.NAME
when not matched then insert (EMAIL, NAME) values (laravel_source.EMAIL, laravel_source.NAME)
```

The `updateOrCreate()` and `upsert()` methods on Eloquent models use the
same statement.

> **NOTE:** Snowflake does not enforce unique constraints. The `uniqueBy`
> columns are the only protection against duplicate rows. Use `upsert()`
> when you must not write the same row two times.

## Insert many rows

An `insert()` with many rows compiles to one statement. One statement is
faster and costs less than many statements. Refer to
[Operation in production](operations.md#costs).

```php
DB::connection('snowflake')->table('events')->insert($rows);
```

## Full-text search

The `whereFullText()` method compiles to the Snowflake
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

You do not need a full-text index. The `fullText()` method in a migration
does not make an index. Refer to [Migrations and schema](migrations.md).

## JSON columns

The JSON methods of the query builder compile to Snowflake functions for
semi-structured data. Refer to
[Semi-structured data](semi-structured-data.md#query-semi-structured-data).

## Time travel

The package adds two query builder methods for
[Snowflake time travel](https://docs.snowflake.com/en/sql-reference/constructs/at-before).
Use them to read a table as it was at an earlier point in time.

### Read a table at a time

The `atTimestamp()` method accepts a `DateTimeInterface` or a string:

```php
DB::connection('snowflake')
    ->table('orders')
    ->atTimestamp(now()->subHour())
    ->get();

// select * from ORDERS at (timestamp => '2026-06-01 12:00:00.000000+00:00'::timestamp_tz)
```

### Read a table before a statement

The `beforeStatement()` method accepts the query ID of a Snowflake
statement:

```php
DB::connection('snowflake')
    ->table('orders')
    ->beforeStatement('8e5d0ca9-005e-44e6-b858-a8f5b37c5726')
    ->get();

// select * from ORDERS before (statement => '8e5d0ca9-005e-44e6-b858-a8f5b37c5726')
```

Use this method to examine the data before an incorrect update or delete.

### Rules for time travel

- Call `atTimestamp()` or `beforeStatement()` after `table()` or `from()`.
  The method changes the current table expression.
- You can add `where()` and the other clauses after the method.
- Snowflake keeps old data only for the data retention period of the table.
  The default period is one day.

## Transactions

The `DB::transaction()` method works. These rules apply:

- Snowflake does not support savepoints. Do not start a transaction in a
  transaction.
- Each DDL statement, for example `CREATE TABLE`, commits the current
  transaction. Refer to
  [Migrations and schema](migrations.md#migrations-are-not-transactional).
- The `lockForUpdate()` and `sharedLock()` methods do nothing. Snowflake has
  no row locks.

## Auto-increment IDs

Snowflake drivers cannot return the ID of the last inserted row. Thus,
`insertGetId()` and `Model::create()` use a different procedure:

1. The package inserts the row.
2. The package reads the highest value of the key column with `max()`.

> **WARNING:** Do not use auto-increment IDs on tables that more than one
> process writes to at the same time. Another process can insert a row
> between step 1 and step 2. The package then gives you the ID of the
> incorrect row.

Use IDs that the application makes, for example UUIDs or ULIDs:

```php
// In the migration
$table->uuid('id')->primary();

// In the model
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Order extends Model
{
    use HasUuids;

    protected $connection = 'snowflake';
}
```

To change how the package finds the ID, refer to
[Extending the package](extending.md#custom-processor).
