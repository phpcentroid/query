<?php



/**
 * Created by PhpStorm.
 * User: kbarbounakis
 * Date: 07/10/16
 * Time: 06:39
 */

namespace PHPCentroid\Query;

abstract class SelectableExpression extends DataQueryExpression
{
    /**
     * Gets or sets a string which represents the alias of this member.
     * @var string
     */
    public ?string $alias;
    /**
     * Gets or sets a string which represents the order of this member if this is going to be used in order expressions.
     * @var string
     */
    public ?string $order;
    /**
     * @param ?string $alias
     * @return SelectableExpression
     */
    public function as(string $alias = NULL): SelectableExpression
    {
        $this->alias = is_null($alias) ? NULL : trim($alias);
        return $this;
    }

    public const ORDER_ASCENDING = 'asc';
    public const ORDER_DESCENDING = 'desc';
    public const OPERATOR_REGEX = '/^(asc|desc)$/i';
    /**
     * @param string $order
     * @return SelectableExpression
     */
    public function orderBy(string $order): SelectableExpression {
        $this->order = strtolower($order);
        return $this;
    }

    public abstract function toArray(): array;

}