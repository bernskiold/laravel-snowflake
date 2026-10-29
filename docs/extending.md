# Extending the package

This page tells you how to replace the query grammar, the schema grammar or
the processor of a connection.

## Replace a grammar or the processor

Set the class names in the `options` array of the connection:

```php
'snowflake' => [
    'driver' => 'snowflake_native',
    // ...
    'options' => [
        'processor' => App\Database\SnowflakeProcessor::class,
        'grammar' => [
            'query' => App\Database\QueryGrammar::class,
            'schema' => App\Database\SchemaGrammar::class,
        ],
    ],
],
```

You can set one, two or all three keys. The package uses its default class
for each key that you do not set.

| Key | Default for `snowflake` and `snowflake_native` | Default for `odbc` |
| --- | --- | --- |
| `processor` | `Bernskiold\LaravelSnowflake\SnowflakeProcessor` | `Illuminate\Database\Query\Processors\Processor` |
| `grammar.query` | `Bernskiold\LaravelSnowflake\Grammars\QueryGrammar` | `Illuminate\Database\Query\Grammars\Grammar` |
| `grammar.schema` | `Bernskiold\LaravelSnowflake\Grammars\SchemaGrammar` | `Illuminate\Database\Schema\Grammars\Grammar` |

We recommend that your class extends the package class. Then you change
only the behavior that you need.

## Custom query grammar

Example: Add a Snowflake function to the query grammar.

```php
namespace App\Database;

use Bernskiold\LaravelSnowflake\Grammars\QueryGrammar as BaseGrammar;
use Illuminate\Database\Query\Builder;

class QueryGrammar extends BaseGrammar
{
    protected function whereDate(Builder $query, $where)
    {
        return 'to_date('.$this->wrap($where['column']).') = '.$this->parameter($where['value']);
    }
}
```

The grammar constructor gets the connection. Do not change the constructor
arguments.

## Custom processor

The processor changes the results of some queries. For example, it finds
the ID after `insertGetId()`. By default, it reads `max()` of the key
column. Refer to [Queries and Eloquent](queries.md#auto-increment-ids).

Example: Stop all calls to `insertGetId()`. This makes sure that your
application uses IDs that it makes itself, for example UUIDs.

```php
namespace App\Database;

use Bernskiold\LaravelSnowflake\SnowflakeProcessor as BaseProcessor;
use Illuminate\Database\Query\Builder;
use LogicException;

class SnowflakeProcessor extends BaseProcessor
{
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        throw new LogicException("Use UUID or ULID keys for the table [{$query->from}].");
    }
}
```

The method gets these arguments:

| Argument | Description |
| --- | --- |
| `$query` | The query builder of the insert. |
| `$sql` | The compiled `insert` statement. |
| `$values` | The bound values of the statement. |
| `$sequence` | The name of the key column. If it is `null`, use `id`. |

If you replace the method, your method must run the insert. Use
`$query->getConnection()->insert($sql, $values)`. Then return the ID.
