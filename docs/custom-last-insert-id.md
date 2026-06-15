# Custom `getLastInsertId()` Function

If you need to provide a custom `getLastInsertId()` function, you can extend
the `SnowflakeProcessor` class and override the function as follows:

```php
use Bernskiold\LaravelSnowflake\SnowflakeProcessor;
use Illuminate\Database\Query\Builder;

class CustomProcessor extends SnowflakeProcessor
{
    /**
     * @param Builder $query
     * @param null $sequence
     * @return mixed
     */
    public function getLastInsertId(Builder $query, $sequence = null)
    {
        return $query->getConnection()->table($query->from)->latest('id')->first()->getAttribute($sequence);
    }
}
```

Then register it on your connection via the `options.processor` configuration
key, as described in [Custom processor and grammars](custom-grammars.md).
