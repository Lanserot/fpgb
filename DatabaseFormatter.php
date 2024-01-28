<?php

namespace FpDbTest;

use Exception;
use mysqli;

class DatabaseFormatter
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function arrayParameter(array $array, bool $processIdentifiers = false): string
    {
        $result = [];

        foreach ($array as $k => $v) {
            if (is_int($k)) {
                $result[] = $processIdentifiers ? $this->parameter($v) : $this->value($v);
            } else {
                $result[] = $this->parameter($k) . ' = ' . $this->value($v); //+
            }
        }

        return implode(', ', $result);
    }

    public function parameter(string $value): string
    {
        return '`' . $this->mysqli->real_escape_string($value) . '`';
    }
    
    public function value($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return $this->mysqli->real_escape_string($value);
        }

        return '\'' . $this->mysqli->real_escape_string($value) . '\'';
    }
}
