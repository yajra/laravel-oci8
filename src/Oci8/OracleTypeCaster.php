<?php

namespace Yajra\Oci8;

class OracleTypeCaster
{
    /**
     * Checks for float and int values
     *
     * @param $value
     *
     * @return int|string
     */
    public static function tryNumeric($value)
    {
        if (is_numeric($value)) {
            return $value + 0;
        }
        return $value;
    }
}
