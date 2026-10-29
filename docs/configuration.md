# Configuration

This page tells you about the connection settings and the package options.

## Drivers

The package adds three database drivers to Laravel:

| Driver | Use |
| --- | --- |
| `snowflake_native` | Connects to Snowflake through the `pdo_snowflake` extension. We recommend this driver. |
| `snowflake` | Connects to Snowflake through the Snowflake ODBC driver. |
| `odbc` | Connects to a data source through a generic ODBC driver. |

The `snowflake_native` and `snowflake` drivers use the same Snowflake query
grammar and schema grammar. The `odbc` driver uses the default Laravel
grammars. Refer to [ODBC connections](odbc.md) for the two ODBC drivers.

## Connection settings

### Native driver

```php
'snowflake' => [
    'driver' => 'snowflake_native',
    'account' => env('SNOWFLAKE_ACCOUNT'),
    'username' => env('SNOWFLAKE_USERNAME'),
    'password' => env('SNOWFLAKE_PASSWORD'),
    'database' => env('SNOWFLAKE_DATABASE'),
    'warehouse' => env('SNOWFLAKE_WAREHOUSE'),
    'schema' => env('SNOWFLAKE_SCHEMA', 'PUBLIC'),
    'role' => env('SNOWFLAKE_ROLE'),
    'options' => [
        // Package options. Refer to "Package options" below.
    ],
],
```

| Key | Necessary | Description |
| --- | --- | --- |
| `driver` | Yes | `snowflake_native`. |
| `account` | Yes | Your Snowflake account identifier, for example `myorg-myaccount` or `xy12345.eu-west-1`. |
| `username` | Yes | The Snowflake user. |
| `password` | For password authentication | The password of the user. |
| `database` | Recommended | The default database. |
| `warehouse` | Recommended | The warehouse that runs the queries. |
| `schema` | Recommended | The default schema. |
| `role` | No | The role for the session. |
| `authenticator` | No | Set to `key_pair` for key-pair authentication. Refer to [Authentication](authentication.md). |
| `options` | No | Package options and PDO attributes. |

### Other DSN parameters

The package adds all other keys in the connection to the DSN. Thus, you can
set each Snowflake connection parameter directly in the connection. For
example, to set a login timeout:

```php
'snowflake' => [
    'driver' => 'snowflake_native',
    // ...
    'logintimeout' => 10,
],
```

The package does not add these keys to the DSN: `driver`, `odbc_driver`,
`dsn`, `options`, `username`, `password`, `name` and `prefix`. The native
driver also does not add `server` and `port`.

> **CAUTION:** The DSN keys are case-sensitive. Use the key names from the
> Snowflake documentation of your driver.

### Full DSN

You can give the full DSN in the `dsn` key. The package then ignores the
other DSN keys:

```php
'snowflake' => [
    'driver' => 'snowflake_native',
    'dsn' => 'account=myorg-myaccount;database=ANALYTICS;warehouse=COMPUTE_WH',
    'username' => env('SNOWFLAKE_USERNAME'),
    'password' => env('SNOWFLAKE_PASSWORD'),
],
```

Do not start the value with `snowflake:`. The package adds this prefix.

## Package options

The package has three options. You can set each option at two levels:

- For all connections, in `config/snowflake.php`.
- For one connection, in the `options` array of the connection. This value
  overrides the value in `config/snowflake.php`.

| Option | Default | Environment variable | Description |
| --- | --- | --- | --- |
| `case_sensitive` | `false` | `SNOWFLAKE_COLUMNS_CASE_SENSITIVE` | Puts table and column names in quotes and keeps their case. |
| `use_ilike` | `true` | `SNOWFLAKE_USE_ILIKE` | Changes `LIKE` to the case-insensitive `ILIKE`. |
| `force_quoted_identifiers` | `true` | `SNOWFLAKE_FORCE_QUOTED_IDENTIFIERS` | Sets `QUOTED_IDENTIFIERS_IGNORE_CASE = false` for each new session. |

The package reads the environment variables only in `config/snowflake.php`.
Thus, the options continue to work after you run `php artisan config:cache`.

Example of options for one connection:

```php
'snowflake' => [
    'driver' => 'snowflake_native',
    // ...
    'options' => [
        'case_sensitive' => true,
        'use_ilike' => false,
    ],
],
```

### Case of table and column names

Snowflake changes names without quotes to uppercase. By default, the package
does the same. It changes all table and column names to uppercase and does
not put them in quotes.

```php
DB::table('orders')->where('customerId', 42)->get();
// select * from ORDERS where CUSTOMERID = ?
```

If your tables use names with quotes and mixed case, set `case_sensitive` to
`true`. The package then puts each name in quotes and keeps its case:

```php
DB::table('orders')->where('customerId', 42)->get();
// select * from "orders" where "customerId" = ?
```

> **CAUTION:** Use the same `case_sensitive` value for all migrations and
> queries on a table. If you change the value, the package cannot find the
> tables and columns that it made before.

### Case-insensitive LIKE

By default, the package changes `LIKE` to `ILIKE`. Thus, `where('name',
'like', 'abc%')` finds `ABC` and `abc`. Most Laravel applications expect this
behavior.

Set `use_ilike` to `false` to keep the case-sensitive `LIKE` of Snowflake.

For one query, you can also use `whereLike()` with the `caseSensitive`
argument. This argument has priority over `use_ilike`:

```php
DB::table('users')->whereLike('name', 'abc%', caseSensitive: true)->get();
// select * from USERS where NAME like ?
```

### Quoted names in the session

For each new session, the package runs this statement:

```sql
ALTER SESSION SET QUOTED_IDENTIFIERS_IGNORE_CASE = false
```

This statement makes sure that names in quotes keep their case, also if your
Snowflake account has a different setting. The package grammar needs this
behavior.

Set `force_quoted_identifiers` to `false` if your Snowflake user cannot run
`ALTER SESSION`. Then make sure that the account or user setting is `false`.

### PDO attributes

You can also put PDO attributes in the `options` array. The package gives
them to PDO:

```php
'options' => [
    PDO::ATTR_TIMEOUT => 30,
],
```
