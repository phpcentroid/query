<?php

namespace PHPCentroid\Query;

class ArithmeticExpression extends SelectableExpression
{

    /**
     * Gets or sets the left operand of this expression.
     * @var DataQueryExpression
     */
    public DataQueryExpression $left;
    /**
     * Gets or sets the right operand of this expression.
     * @var DataQueryExpression
     */
    public DataQueryExpression $right;
    /**
     * Gets or sets the operator used on expression.
     * @var ArithmeticOperatorEnum
     */
    public ArithmeticOperatorEnum $operator;
    /**
     * ComparisonExpression constructor.
     * @param mixed $left - The left operand
     * @param string $op - The operator of this expression
     * @param mixed $right - The right operand
     */
    public function __construct(mixed $left, ArithmeticOperatorEnum $op, mixed $right)
    {
        if (is_string($left))
            $this->left = new MemberExpression($left);
        else {
            $this->left = $left;
        }
        $this->operator = $op;
        if ($right instanceof DataQueryExpression)
            $this->right = $right;
        else
            $this->right = new LiteralExpression($right);
    }

    const OPERATOR_REGEX = '/^(add|sub|mul|div|mod)$/';

    public function to_str($formatter = NULL): string
    {
        if ($formatter instanceof iExpressionFormatter)
            return $formatter->format($this);
        return '(' . DataQueryExpression::escape($this->left) . ' ' . $this->operator->value . ' ' . DataQueryExpression::escape($this->right) . ')';
    }


    public function toArray(): array
    {
        $operator = $this->operator->value;
        return [
            "$$operator" => [
                $this->left->toArray(),
                $this->right->toArray()
            ]
        ];
    }
}