# Getting started

This page tells you how to install the package and connect Laravel to
Snowflake.

## Requirements

- PHP 8.2 or higher.
- Laravel 12 or higher.
- One of these Snowflake drivers:
  - The native driver: the
    [`pdo_snowflake`](https://github.com/snowflakedb/pdo_snowflake) PHP
    extension. We recommend this driver.
  - The [Snowflake ODBC driver](https://docs.snowflake.com/en/developer-guide/odbc/odbc),
    with the PHP `odbc` and `pdo_odbc` extensions.
- A Snowflake user that has access to a database, a schema and a warehouse.

## Install the package

1. Run this command in the root directory of your application:

   ```bash
   composer require bernskiold/laravel-snowflake
   ```

2. Make sure that the service provider is registered.

   Laravel package discovery registers the service provider automatically.
   If you disabled package discovery, add the service provider to your
   application manually:

   ```php
   Bernskiold\LaravelSnowflake\SnowflakeServiceProvider::class,
   ```

3. Optional: Publish the package configuration file:

   ```bash
   php artisan vendor:publish --tag=snowflake-config
   ```

   This command writes the file `config/snowflake.php`. You need this file
   only if you want to change the package defaults. Refer to
   [Configuration](configuration.md#package-options).

## Make sure that the native driver is installed

1. Run this command:

   ```bash
   php -m | grep pdo_snowflake
   ```

2. Make sure that the output shows `pdo_snowflake`.

   If the output is empty, install the extension. Refer to the
   [`pdo_snowflake` installation instructions](https://github.com/snowflakedb/pdo_snowflake).

If you cannot install the native driver, use the ODBC driver. Refer to
[ODBC connections](odbc.md).

## Add a connection

1. Add these values to your `.env` file:

   ```dotenv
   SNOWFLAKE_ACCOUNT=myorg-myaccount
   SNOWFLAKE_USERNAME=LARAVEL_APP
   SNOWFLAKE_PASSWORD=secret
   SNOWFLAKE_DATABASE=ANALYTICS
   SNOWFLAKE_WAREHOUSE=COMPUTE_WH
   SNOWFLAKE_SCHEMA=PUBLIC
   ```

2. Add a connection to the `connections` array in `config/database.php`:

   ```php
   'snowflake' => [
       'driver' => 'snowflake_native',
       'account' => env('SNOWFLAKE_ACCOUNT'),
       'username' => env('SNOWFLAKE_USERNAME'),
       'password' => env('SNOWFLAKE_PASSWORD'),
       'database' => env('SNOWFLAKE_DATABASE'),
       'warehouse' => env('SNOWFLAKE_WAREHOUSE'),
       'schema' => env('SNOWFLAKE_SCHEMA', 'PUBLIC'),
   ],
   ```

3. Optional: Make Snowflake the default connection. Set this value in your
   `.env` file:

   ```dotenv
   DB_CONNECTION=snowflake
   ```

> **NOTE:** For production, we recommend key-pair authentication. Refer to
> [Authentication](authentication.md).

## Test the connection

1. Run this command:

   ```bash
   php artisan db:show --database=snowflake
   ```

2. Make sure that the output shows your database and its tables.

If the command fails, examine the error message. These are the usual
causes:

| Error | Cause | Correction |
| --- | --- | --- |
| `Native Snowflake driver pdo_snowflake was not enabled` | PHP did not load the `pdo_snowflake` extension. | Install and enable the extension. |
| Authentication failure | The user name, the password or the account is incorrect. | Examine the values in your `.env` file. |
| No active warehouse | The `warehouse` value is missing, or the user cannot use the warehouse. | Set `warehouse` and give the user the `USAGE` privilege on the warehouse. |

## Run your first query

Use the query builder or Eloquent as you do with other databases:

```php
use Illuminate\Support\Facades\DB;

$orders = DB::connection('snowflake')
    ->table('orders')
    ->where('status', 'shipped')
    ->get();
```

To use a model with the connection, set the `$connection` property:

```php
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $connection = 'snowflake';
}
```

## Next steps

- Read [Queries and Eloquent](queries.md) to learn how Snowflake changes
  query behavior.
- Read [Limitations](limitations.md) before you write to Snowflake from your
  application.
- Read [Operation in production](operations.md) before you deploy.
