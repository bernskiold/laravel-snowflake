# Laravel Snowflake documentation

This documentation tells you how to install, configure and use the
`bernskiold/laravel-snowflake` package.

## Contents

1. [Getting started](getting-started.md) — Install the package and make your
   first connection.
2. [Configuration](configuration.md) — Connection settings, drivers and
   package options.
3. [Authentication](authentication.md) — Password and key-pair
   authentication.
4. [Queries and Eloquent](queries.md) — Query builder and Eloquent behavior
   on Snowflake.
5. [Semi-structured data](semi-structured-data.md) — Write and read
   `VARIANT`, `OBJECT` and `ARRAY` data.
6. [Migrations and schema](migrations.md) — Column types, constraints and
   schema inspection.
7. [Limitations](limitations.md) — Operations that Snowflake does not
   support.
8. [Operation in production](operations.md) — Connections, costs and
   recovery.
9. [ODBC connections](odbc.md) — Use the Snowflake ODBC driver or a generic
   ODBC data source.
10. [Extending the package](extending.md) — Replace the grammars or the
    processor.

## Terms

| Term | Meaning |
| --- | --- |
| Native driver | The `pdo_snowflake` PHP extension from Snowflake. |
| ODBC driver | The Snowflake ODBC driver, used through the `pdo_odbc` PHP extension. |
| Connection | An entry in `config/database.php` that uses one of the package drivers. |
| Warehouse | The Snowflake compute resource that runs your queries. |
| DSN | The connection string that the package gives to PDO. |
