# Limitations

This page tells you about Laravel features that do not work on Snowflake.

## Operations that give an error

Snowflake has no equivalent for these operations. The package gives a
`RuntimeException` with a clear message. It does not send incorrect SQL to
Snowflake.

| Operation | Alternative |
| --- | --- |
| `insertOrIgnore()` and `insertOrIgnoreUsing()` | Use `upsert()`. |
| `update()` with `join()` | Write a `MERGE` statement with `DB::statement()`. |
| `delete()` with `join()` | Write a `MERGE` statement with `DB::statement()`. |
| `update()` with `limit()` | Select the keys first. Then update with `whereIn()`. |
| `delete()` with `limit()` | Select the keys first. Then delete with `whereIn()`. |
| `update()` on one JSON key, for example `settings->theme` | Update the full column. Refer to [Semi-structured data](semi-structured-data.md#update-semi-structured-data). |
| Computed column without a type | Give a type with `virtualAs()` or `storedAs()`. |

Example of a `MERGE` statement in place of an update with a join:

```php
DB::connection('snowflake')->statement(<<<'SQL'
    merge into ORDERS
    using CUSTOMERS
    on ORDERS.CUSTOMER_ID = CUSTOMERS.ID
    when matched and CUSTOMERS.STATUS = 'closed' then
        update set ORDERS.STATUS = 'cancelled'
SQL);
```

## Operations that do nothing

These operations do not give an error. They have no effect on Snowflake:

| Operation | Reason |
| --- | --- |
| `lockForUpdate()` and `sharedLock()` | Snowflake has no row locks. |
| `index()`, `fullText()` and `spatialIndex()` in migrations | Snowflake standard tables have no indexes. |
| `useCurrentOnUpdate()` in migrations | Snowflake does not support this modifier. |
| `Schema::enableForeignKeyConstraints()` and `Schema::disableForeignKeyConstraints()` | Snowflake does not enforce foreign keys. |

## Behavior that is different

| Feature | Behavior on Snowflake |
| --- | --- |
| Nested transactions | Snowflake does not support savepoints. Do not start a transaction in a transaction. |
| DDL in transactions | Each DDL statement commits immediately. Refer to [Migrations and schema](migrations.md#migrations-are-not-transactional). |
| Unique, primary key and foreign key constraints | Snowflake makes them but does not enforce them. |
| `enum()` columns | The column is a `varchar`. Snowflake does not limit the values. |
| `insertGetId()` and `Model::create()` | The package reads `max()` of the key column. This is not safe with concurrent writes. Refer to [Queries and Eloquent](queries.md#auto-increment-ids). |
