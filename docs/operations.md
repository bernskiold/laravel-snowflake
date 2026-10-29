# Operation in production

This page tells you what to know before you use Snowflake from a Laravel
application in production.

## Snowflake is an analytical database

Snowflake is fast for large scans, aggregations and reports. It is slow for
the read and write of single rows. This is the design of Snowflake.

- Use Snowflake for reports, data exports and batch jobs.
- Do not use Snowflake for sessions, the cache, queues or the data of each
  web request. Use an OLTP database, for example PostgreSQL or MySQL, for
  these.

## Connections

A new Snowflake connection can take 1 to 2 seconds. The driver must open a
session with the Snowflake cloud service.

- Long-running processes, for example queue workers and Laravel Octane,
  open the connection one time and use it again.
- Short processes, for example a PHP-FPM request, open a new connection each
  time. Each request then waits for the connection.

We recommend that you send Snowflake work to queued jobs.

## Recovery from a broken connection

Snowflake can close a session, for example after a long time without
activity. The driver then gives a connection error for the next query.

The package recovers from this error:

1. The query fails with a `QueryException`.
2. The package disconnects the broken connection.
3. The next query opens a new connection.

The package does not run the failed query again. A write can be complete on
Snowflake before the connection broke. If the package ran the write again,
Snowflake could apply it two times.

Thus, your application must decide if it runs the query again. In a queued
job, use the retry behavior of the queue:

```php
class ExportOrders implements ShouldQueue
{
    public $tries = 3;

    public $backoff = 10;
}
```

> **NOTE:** The package finds a broken connection from the SQLSTATE code of
> the error. All codes that start with `08` are connection errors.

## Costs

Snowflake charges for the time that a warehouse runs. Each query starts the
warehouse or keeps it on. Many small queries can cost more than one large
query.

To decrease costs:

- Load relations with `with()`. Do not load them in a loop (the N+1
  problem).
- Write many rows with one `insert()` or one `upsert()`.
- Select only the columns that you need.
- Set a short auto-suspend time on the warehouse.
- Use a different warehouse for the application and for other work. Then
  you can see the costs of each.

## Timeouts

Use DSN parameters to set timeouts for the driver. For example, use
`logintimeout` to stop a connection attempt that takes too long:

```php
'snowflake' => [
    'driver' => 'snowflake_native',
    // ...
    'logintimeout' => 10,
],
```

Refer to the documentation of your driver for the parameter names.
