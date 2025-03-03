<?php

namespace PHPCentroid\Query;

class LiteralExpression extends SelectableExpression
{

    /**
     * Gets or sets a string which represents the name of this member.
     * @var string
     */
    public mixed $value;

    /**
     * LiteralExpression constructor.
     * @param mixed $value
     */
    public function __construct(mixed $value)
    {
        $this->value = $value;
    }

    /**
     * @param mixed $value
     * @return LiteralExpression
     */
    public static function create(mixed $value): LiteralExpression {
        return new LiteralExpression($value);
    }

    public function to_str($formatter = NULL): string
    {
        if ($formatter instanceof iExpressionFormatter)
            return $formatter->format($this);
        return DataQueryExpression::escape($this->value);
    }


    public function toArray(): array
    {
        if ($this->value instanceof DataQueryExpression) {
            return array('$literal' => $this->value->toArray());
        }
        return array(
            '$literal' => $this->value
        );
    }
}