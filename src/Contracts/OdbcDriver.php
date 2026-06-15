<?php

namespace Bernskiold\LaravelSnowflake\Contracts;

use Closure;

interface OdbcDriver
{
    public static function registerDriver(): Closure;
}
