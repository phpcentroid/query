<?php

/**
 * Created by PhpStorm.
 * User: kbarbounakis
 * Date: 07/10/16
 * Time: 06:38
 */

namespace PHPCentroid\Query;

abstract class DataQueryExpression
{
    /**
     * @param mixed $formatter
     * @return string
     */
    abstract public function to_str(mixed $formatter = NULL): string;

    public function __toString()
    {
        return $this->to_str();
    }

    /** @noinspection PhpMixedReturnTypeCanBeReducedInspection */
    public static function escape($value = null): mixed {
        //0. null
        if (is_null($value))
            return 'null';
        //1. array
        if (is_array($value)) {
            $array = array();
            foreach ($value as $val) {
                $array[] = DataQueryExpression::escape($val);
            }
            return '['. implode(",", $array) . ']';
        }
        //2. datetime
        else if (is_a($value, 'DateTime')) {
            $str = $value->format('c');
            return "'$str'";
        }
        //3. boolean
        else if (is_bool($value)) {
            return $value ? 'true': 'false';
        }
        //4. numeric
        else if (is_float($value) || is_double($value) || is_int($value)) {
            return json_encode($value);
        }
        //5. string
        else if (is_string($value)) {
            // an important exception here:
            // remove already escaped dollar sign at the beggining of the string
            if (preg_match('/^\\$/', $value)) {
                return "'" . substr($value, 1) . "'";
            }
            return "'$value'";
        }
        //6. query expression
        else if ($value instanceof DataQueryExpression) {
            return (string)$value;
        }
        //7. other
        else {
            $str = (string)$value;
            return "'$str'";
        }
    }

    public abstract function toArray(): array;

}