# Migrations and schema

This page tells you how Laravel migrations and the schema builder work on
Snowflake.

## Run migrations on Snowflake

If Snowflake is not your default connection, set the connection in the
migration:

```php
return new class extends Migration
{
    protected $connection = 'snowflake';

    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('status', 20);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });
    }
};
```

You can also give the connection to the command:

```bash
php artisan migrate --database=snowflake
```

## Migrations are not transactional

Snowflake commits each DDL statement immediately. Laravel cannot roll back a
migration that fails before it is complete.

> **CAUTION:** If a migration fails, the database can contain a part of the
> changes. Examine the schema before you run the migration again.

To decrease this risk:

- Put few changes in each migration.
- Write migrations that you can run again without errors. For example, use
  `Schema::hasTable()` and `Schema::hasColumn()` before you make a change.

## Column types

The schema grammar changes the Laravel column types to these Snowflake
types:

| Laravel method | Snowflake type |
| --- | --- |
| `string($name, $length)` | `varchar($length)` |
| `char($name, $length)` | `char($length)` |
| `text()`, `tinyText()`, `mediumText()`, `longText()` | `text` |
| `tinyInteger()` | `tinyint` |
| `smallInteger()` | `smallint` |
| `integer()`, `mediumInteger()` | `int` |
| `bigInteger()` | `bigint` |
| `id()`, `increments()` and the other auto-increment methods | The integer type with `autoincrement` |
| `float()` | `float` |
| `double()` | `double` |
| `decimal($name, $total, $places)` | `decimal($total, $places)` |
| `boolean()` | `boolean` |
| `date()` | `date` |
| `dateTime()`, `timestamp()` | `timestamp_ntz` |
| `dateTimeTz()`, `timestampTz()` | `timestamp_tz` |
| `time()`, `timeTz()` | `time` |
| `year()` | `smallint` |
| `json()`, `jsonb()` | `variant` |
| `binary()` | `binary` |
| `uuid()` | `char(36)` |
| `ipAddress()` | `varchar(45)` |
| `macAddress()` | `varchar(17)` |
| `enum()`, `set()` | `varchar` |
| `geometry()` | `geometry` |
| `geography()` | `geography` |

These rules apply:

- Snowflake has no time zone for the `time` type. Thus, `timeTz()` makes a
  `time` column.
- Snowflake has no enum type. An `enum()` column does not limit the values.
  Do the validation in your application.
- The `useCurrent()` modifier adds `default CURRENT_TIMESTAMP`.
- The `useCurrentOnUpdate()` modifier does nothing. Snowflake does not
  support it.
- Computed columns need a type. Use the `virtualAs()` or `storedAs()`
  modifier with a type.

## Indexes and constraints

Snowflake standard tables have no indexes. Thus:

- The `index()`, `fullText()` and `spatialIndex()` methods do nothing.
- `Schema::getIndexes()` gives an empty list.

Snowflake makes primary key, unique and foreign key constraints, but it does
not enforce them:

- A primary key does not prevent duplicate rows.
- A unique constraint does not prevent duplicate values.
- A foreign key does not prevent rows without a related row.

`Schema::getForeignKeys()` gives an empty list.

> **CAUTION:** Make sure that your application prevents duplicate rows. Use
> `upsert()` to write rows that can exist already. Refer to
> [Queries and Eloquent](queries.md#upserts).

## Change columns

The `change()` modifier compiles to `alter table ... modify column`.
Snowflake permits only some changes to a column. For example, you can make a
`varchar` longer, but you cannot change a `varchar` to a number. Refer to
the [Snowflake `ALTER TABLE` documentation](https://docs.snowflake.com/en/sql-reference/sql/alter-table-column).

## Schema inspection

The schema builder reads the Snowflake `information_schema`. These methods
and commands work:

- `Schema::hasTable()` and `Schema::hasColumn()`.
- `Schema::getTables()`, `Schema::getViews()` and `Schema::getColumns()`.
- `Schema::dropAllTables()` and `Schema::dropAllViews()`.
- `Schema::createDatabase()`, `Schema::dropDatabase()` and
  `Schema::dropDatabaseIfExists()`.
- The `db:show`, `db:table` and `model:show` Artisan commands.

The schema builder reads only the `schema` of the connection. If the
connection has no `schema` value, the schema builder reads all schemas in
the database, but not `INFORMATION_SCHEMA`.
