<?php
/**
 * Created by PhpStorm.
 * User: kbarbounakis
 * Date: 08/10/16
 * Time: 08:05
 */

namespace PHPCentroid\Query;
use ArrayObject;

class MemberListExpression extends ArrayObject
{

    public function __construct($input)
    {
        $arr = array();
        if (is_array($input)) {
            foreach ($input as $item) {
                if (is_string($item))
                    $arr[] = new MemberExpression($item);
                else {
                    $arr[] = $item;
                }
            }
        }
        parent::__construct($arr);
    }

    public function count(): int
    {
        return parent::count();
    }

    /**
     * @param SelectableExpression $value
     */
    public function append($value)
    {
        if (is_string($value)) {
            parent::append(new MemberExpression($value));
        }
        else {
            parent::append($value);
        }

    }

    public function offsetSet($key, $value)
    {
        if (is_string($value)) {
            parent::offsetSet($key, new MemberExpression($value));
        }
        else {
            parent::offsetSet($key, $value);
        }
    }

    /**
     * @param mixed $formatter
     * @return ?string
     */
    public function to_str($formatter = NULL): ?string
    {
        if (!is_null($formatter)) {
            $formatter->call($this);
        }
        if ($this->count()==0) {
            return NULL;
        }
        /**
         * @param DataQueryExpression $expr
         * @return string
         */
        $map = function(DataQueryExpression $expr) {
            return $expr->to_str();
        };
        return implode(',', array_map($map, $this->getArrayCopy()));
    }
}