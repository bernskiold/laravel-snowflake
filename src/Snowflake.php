<?php

namespace Bernskiold\LaravelSnowflake;

use Bernskiold\LaravelSnowflake\Values\Variant;

class Snowflake
{
    /**
     * Wrap a value as Snowflake semi-structured data so it is written through
     * PARSE_JSON() into a VARIANT / OBJECT / ARRAY column.
     */
    public static function variant(mixed $value): Variant
    {
        return Variant::make($value);
    }
}
