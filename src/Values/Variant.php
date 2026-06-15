<?php

namespace Bernskiold\LaravelSnowflake\Values;

use const JSON_THROW_ON_ERROR;

use JsonSerializable;

use function is_string;

/**
 * Marks a value as Snowflake semi-structured data (VARIANT / OBJECT / ARRAY).
 *
 * The query grammar renders a Variant binding as PARSE_JSON(?) so Snowflake
 * parses the JSON payload into a semi-structured value instead of rejecting a
 * plain string literal. The connection serialises the wrapped value to a JSON
 * string at bind time, which keeps the value flowing as a real bound parameter.
 */
final class Variant implements JsonSerializable
{
    public function __construct(protected mixed $value) {}

    /**
     * Wrap a value as a Variant, leaving an existing Variant untouched.
     */
    public static function make(mixed $value): self
    {
        return $value instanceof self ? $value : new self($value);
    }

    /**
     * The original, unwrapped value.
     */
    public function value(): mixed
    {
        return $this->value;
    }

    /**
     * The JSON payload to bind for PARSE_JSON().
     *
     * A value that is already a string is treated as pre-encoded JSON; anything
     * else is encoded. Null is preserved so PARSE_JSON(NULL) yields SQL NULL.
     */
    public function toJsonString(): ?string
    {
        if ($this->value === null) {
            return null;
        }

        if (is_string($this->value)) {
            return $this->value;
        }

        return json_encode($this->value, JSON_THROW_ON_ERROR);
    }

    public function jsonSerialize(): mixed
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return (string) $this->toJsonString();
    }
}
