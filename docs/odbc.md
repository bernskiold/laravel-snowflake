# ODBC connections

This page tells you how to connect through ODBC. The package has two ODBC
drivers:

- `snowflake`: Snowflake through the Snowflake ODBC driver.
- `odbc`: A generic ODBC data source.

We recommend the native driver for Snowflake. Use the ODBC driver only if
you cannot install the `pdo_snowflake` extension. Refer to
[Getting started](getting-started.md).

## Snowflake through ODBC

### Requirements

- The [Snowflake ODBC driver](https://docs.snowflake.com/en/developer-guide/odbc/odbc),
  version 2.21.6 or higher.
- The PHP `odbc` and `pdo_odbc` extensions.

### Configure the Snowflake ODBC driver

The Snowflake ODBC driver runs DDL statements when it prepares them. Laravel
prepares each statement before it runs it. Thus, the driver can run a DDL
statement two times.

To prevent this problem, set this parameter in the configuration of the
driver:

```ini
NoExecuteInSQLPrepare=true
```

- On Linux and macOS, add the parameter to the `simba.snowflake.ini` file.
- On Windows, add the parameter in the ODBC Data Source Administrator.

Refer to the
[Snowflake ODBC parameters](https://docs.snowflake.com/en/developer-guide/odbc/odbc-parameters).

### Add the connection

```php
'snowflake' => [
    'driver' => 'snowflake',
    'odbc_driver' => env('SNOWFLAKE_ODBC_DRIVER'),
    'server' => env('SNOWFLAKE_ACCOUNT').'.snowflakecomputing.com',
    'username' => env('SNOWFLAKE_USERNAME'),
    'password' => env('SNOWFLAKE_PASSWORD'),
    'database' => env('SNOWFLAKE_DATABASE'),
    'warehouse' => env('SNOWFLAKE_WAREHOUSE'),
    'schema' => env('SNOWFLAKE_SCHEMA', 'PUBLIC'),
    'options' => [
        PDO::ODBC_ATTR_USE_CURSOR_LIBRARY => PDO::ODBC_SQL_USE_DRIVER,
    ],
],
```

| Key | Necessary | Description |
| --- | --- | --- |
| `driver` | Yes | `snowflake`. |
| `odbc_driver` | Yes | The absolute path to the driver library, or the driver name in `odbcinst.ini`. |
| `server` | Yes | The host name of your Snowflake account. |
| `options` | Yes | Must contain `PDO::ODBC_ATTR_USE_CURSOR_LIBRARY => PDO::ODBC_SQL_USE_DRIVER`. |

Examples of values for `odbc_driver`:

| Operating system | Example |
| --- | --- |
| macOS | `/opt/snowflake/snowflakeodbc/lib/universal/libSnowflake.dylib` |
| Linux | `/usr/lib64/snowflake/odbc/lib/libSnowflake.so` |
| Name in `odbcinst.ini` | `SnowflakeDSIIDriver` |

All other keys go to the DSN. Refer to
[Configuration](configuration.md#other-dsn-parameters).

If you do not set `odbc_driver`, the connection fails with an error.

### Bound values

The Snowflake ODBC driver cannot send bound values in the usual way. Thus,
the package uses its own statement class for ODBC connections. This class
puts the values into the SQL text before it runs the statement:

- `null` becomes `null`.
- Booleans become `TRUE` and `FALSE`.
- Integers and floats become numbers.
- The class escapes strings and puts them in single quotes. It escapes
  quotes and backslashes.

The native driver does not use this class. It sends bound values to
Snowflake.

> **CAUTION:** The statement class finds each parameter from the `?`
> character. Do not use a `?` character in the SQL text of a raw query on
> an ODBC connection, for example in a string literal. Give the value as a
> bound parameter.

The class also runs `CREATE`, `ALTER`, `DROP` and `TRUNCATE` statements
directly, without a prepare step.

## Generic ODBC data sources

Use the `odbc` driver for a data source that is not Snowflake. This driver
uses the default Laravel query grammar and schema grammar.

Give a DSN name from your ODBC manager:

```php
'warehouse' => [
    'driver' => 'odbc',
    'dsn' => 'MyDataSource',
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
],
```

Or give a full connection string:

```php
'warehouse' => [
    'driver' => 'odbc',
    'dsn' => 'Driver={My Driver};Server=db.example.com;Port=443;Database=Sales',
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
],
```

The package adds the `odbc:` prefix to the DSN.

If you do not set `dsn`, the package makes the DSN from `odbc_driver` and
the other keys of the connection. This is the same procedure as for the
`snowflake` driver.

### Custom statement class

To use a different PDO statement class, set the `flavour` option:

```php
'options' => [
    'flavour' => App\Database\MyStatement::class,
],
```

### Custom grammars

To use a different grammar or processor for a generic connection, refer to
[Extending the package](extending.md).

## Troubleshooting

| Problem | Correction |
| --- | --- |
| `internal error, unexpected SHLIBEXT value` | Add `PDO::ODBC_ATTR_USE_CURSOR_LIBRARY => PDO::ODBC_SQL_USE_DRIVER` to `options`. |
| A migration makes a table, then fails because the table exists | Set `NoExecuteInSQLPrepare=true` in the driver configuration. |
| `Please make sure the environment variable: "DB_ODBC_DRIVER" was set properly` | Set `odbc_driver` in the connection. |
| PHP cannot find the driver | Make sure that the `odbc` and `pdo_odbc` extensions are installed. Run `php -m` to examine them. |
