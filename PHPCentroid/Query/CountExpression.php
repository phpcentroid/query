<?php

namespace PHPCentroid\Query;

class CountExpression extends MethodCallExpression
{
    public function __construct($arg = NULL)
    {
        if (is_null($arg)) {
            parent::__construct('count');
            return;
        }
        parent::__construct('count', array($arg));
    }

    public function toArray(): array
    {
        $arg = current($this->args);
        if ($arg instanceof DataQueryExpression) {
            return [
                '$count' => $arg->toArray()
            ];
        }
        return [
            '$count' => (array)$arg
        ];
    }
}