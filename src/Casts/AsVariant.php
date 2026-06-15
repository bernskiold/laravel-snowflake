<?php

namespace Bernskiold\LaravelSnowflake\Casts;

use Bernskiold\LaravelSnowflake\Values\Variant;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

use function is_string;

/**
 * Eloquent cast that writes an attribute as a Snowflake VARIANT (through
 * PARSE_JSON) and reads it back as a decoded PHP value.
 *
 * @implements CastsAttributes<mixed, mixed>
 */
class AsVariant implements CastsAttributes
{
    /**
     * Cast the stored value to a decoded PHP value.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value instanceof Variant) {
            return $value->value();
        }

        if ($value === null) {
            return null;
        }

        return is_string($value) ? json_decode($value, true) : $value;
    }

    /**
     * Prepare the value to be stored as a Snowflake VARIANT.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value === null ? null : Variant::make($value);
    }
}
