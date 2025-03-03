<?php
/**
 * Created by PhpStorm.
 * User: kbarbounakis
 * Date: 18/10/2016
 * Time: 9:18 μμ
 */

namespace PHPCentroid\Query;

class JoinExpression extends DataQueryExpression
{
    /**
     * @var string
     */
    private JoinDirectionEnum $direction;
    /**
     * @var EntityExpression
     */
    private EntityExpression $entity;

    /**
     * @var ComparisonExpression|LogicalExpression
     */
    private mixed $expr;

    /**
     * JoinExpression constructor.
     * @param string|EntityExpression $entity
     * @param string $direction
     */
    public function __construct(mixed $entity, JoinDirectionEnum $direction = JoinDirectionEnum::INNER)
    {
        if (is_string($entity)) {
            $this->entity = new EntityExpression($entity);
            $this->direction = $direction;
            return;
        }
        $this->entity = $entity;
    }

    /**
     * @param ComparisonExpression|LogicalExpression $expr
     * @return $this
     */
    public function with(mixed $expr): JoinExpression
    {
        $this->expr = $expr;
        return $this;
    }

    /**
     * @param mixed|null $formatter
     * @return mixed
     */
    public function to_str(mixed $formatter = NULL): string
    {
        if ($formatter instanceof iExpressionFormatter)
            return $formatter->format($this);
        if ($this->entity->alias === NULL)
            return $this->entity->name.'('.$this->expr->to_str().')';
        else
            return $this->entity->name.'('.$this->expr->to_str().') as '.$this->entity->alias;
    }

    public function toArray(): array
    {
        return [
            '$lookup' => [
                'from' => $this->entity->name,
                'direction' => $this->direction,
                'with' => $this->expr->toArray(),
                'as' => $this->entity->alias
            ]
        ];
    }
}