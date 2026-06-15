<?php

namespace Bernskiold\LaravelSnowflake\Tests\Fixtures;

use PDO;
use PDOStatement;

class BindingCapturingStatement extends PDOStatement
{
    public array $bound = [];

    public function __construct() {}

    public function bindValue($param, $value, $type = PDO::PARAM_STR): bool
    {
        $this->bound[$param] = [$value, $type];

        return true;
    }
}
