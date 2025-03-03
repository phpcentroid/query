<?php



/**
 * Created by PhpStorm.
 * User: kbarbounakis
 * Date: 07/10/16
 * Time: 06:39
 */

namespace PHPCentroid\Query;

class EntityExpression extends DataQueryExpression
{
    /**
     * Gets or sets a string which represents the alias of this entity.
     * @var string
     */
    public $alias;
    /**
     * Gets or sets a string which represents the name of this entity.
     * @var string
     */
    public $name;
    /**
     * EntityExpression constructor.
     * @param string $name
     * @param ?string $alias
     */
    public function __construct(string $name, ?string $alias = NULL)
    {
        $this->name = $name;
        $this->alias = $alias;
    }

    public static function create(string $name): EntityExpression
    {
        return new EntityExpression($name);
    }

    /**
     * @param ?string $alias
     * @return EntityExpression
     */
    public function with_alias(?string $alias = NULL): EntityExpression{
        $this->alias = is_null($alias) ? NULL : $alias;
        return $this;
    }

    public function to_str($formatter = NULL): string
    {
        return $this->name;
    }


    public function toArray(): array
    {
        if (isset($this->alias)) {
            return [
                $this->alias => $this->name
            ];
        } else {
            return [
                $this->name => 1
            ];
        }
    }
}