<?php
/**
 * Created by PhpStorm.
 * User: kbarbounakis
 * Date: 07/10/16
 * Time: 07:26
 */

namespace PHPCentroid\Query;

class MethodCallExpression extends SelectableExpression
{
    /**
     * Gets or sets a string which represents the name of the method.
     * @var string
     */
    public string $method;
    /**
     * Gets or sets an array which represents the arguments of this method.
     * @var array
     */
    public array $args = array();

    /**
     * MethodCallExpression constructor.
     * @param string $method - The name of the method
     * @param array|string|DataQueryExpression|null $arg - An argument or an array of arguments
     */
    public function __construct(string $method, array|string|DataQueryExpression $arg = NULL)
    {
        if (!is_null($arg)) {
            if (is_array($arg)) {
                foreach ($arg as $arg1) {
                    $this->do_add_argument($arg1);
                }
            }
            else {
                $this->do_add_argument($arg);
            }
        }
        $this->method = $method;
    }

    private function do_add_argument($arg) {
        if (is_string($arg)) {
            $this->args[] = new MemberExpression($arg);
        }
        else {
            $this->args[] = $arg;
        }
    }

    public function to_str($formatter = NULL): string
    {
        $array = array();
        if ($formatter instanceof iExpressionFormatter)
            return $formatter->format($this);
        return $this->method.'('.implode(',',$array).')';
    }

    public function toArray(): array
    {
        $args = array();
        foreach ($this->args as $arg) {
            if ($arg instanceof DataQueryExpression) {
                $args[] = $arg->toArray();
            } else {
                $args[] = (array)$arg;
            }
        }
        return array("$$this->method" => $args);
    }
}