# Custom Processor / QueryGrammar / SchemaGrammar

To use a custom class instead of the default one, you can update your connection
configuration as follows:

```php
'snowflake' => [
    //...
    'options' => [
        'processor' => Bernskiold\LaravelSnowflake\SnowflakeProcessor::class,
        'grammar' => [
            'query' => Bernskiold\LaravelSnowflake\Grammars\QueryGrammar::class,
            'schema' => Bernskiold\LaravelSnowflake\Grammars\SchemaGrammar::class,
        ],
    ],
],
```

> Values given above are the defaults for Snowflake connections. Generic
> `odbc` connections fall back to the default Illuminate processor and
> grammars when no options are provided.
