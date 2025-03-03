<?php

namespace PHPCentroid\Query;

class ComparisonExpression extends DataQueryExpression
{

    /**
     * Gets or sets the left operand of this expression.
     * @var mixed
     */
    public mixed $left;
    /**
     * Gets or sets the right operand of this expression.
     * @var mixed
     */
    public mixed $right;
    /**
     * Gets or sets the operator used on expression.
     * @var string
     */
    public string $operator;
    /**
     * ComparisonExpression constructor.
     * @param mixed $left - The left operand
     * @param string $op - The operator of this expression
     * @param mixed $right - The right operand
     */
    public function __construct($left, string $op, $right)
    {
        if (is_string($left))
            $this->left = new MemberExpression($left);
        else {
            $this->left = $left;
        }
        $this->operator = $op;
        $this->set_right($right);
    }

    public function set_right($right) {
        if ($right instanceof DataQueryExpression)
            $this->right = $right;
        else
            $this->right = new LiteralExpression($right);
    }

    const OPERATOR_REGEX = '/^(eq|ne|le|lt|ge|gt|in|nin)$/';

    const OPERATOR_EQUAL = 'eq';
    const OPERATOR_NOT_EQUAL = 'ne';
    const OPERATOR_LOWER = 'lt';
    const OPERATOR_LOWER_OR_EQUAL = 'le';
    const OPERATOR_GREATER = 'gt';
    const OPERATOR_GREATER_OR_EQUAL = 'ge';
    const OPERATOR_IN = 'in';
    const OPERATOR_NIN = 'nin';

    public function to_str($formatter = NULL): string
    {
        if ($formatter instanceof iExpressionFormatter)
            return $formatter->format($this);
        return DataQueryExpression::escape($this->left).' '.$this->operator.' '.DataQueryExpression::escape($this->right);
    }

    public function toArray(): array
    {
        $left = $this->left;
        if ($left instanceof DataQueryExpression) {
            $left = $left->toArray();
        }
        $right = $this->right;
        if ($right instanceof DataQueryExpression) {
            $right = $right->toArray();
        }
        return ["$$this->operator" => [$left, $right]];
    }


}