<?php
/**
 * Created by PhpStorm.
 * User: kbarbounakis
 * Date: 07/10/16
 * Time: 10:00
 */

namespace PHPCentroid\Query;


use Closure;
use Error;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use function _\size;

class SqlFormatter implements iExpressionFormatter
{
    public array $settings = array('nameFormat' => '`$1`');

    protected ObjectNameValidator $nameValidator;

    protected array $methods = [];

    public function __construct()
    {
        $reflectionClass = new ReflectionClass($this);
        $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);
        $methods = array_filter($methods, function($x) {
            return current($x->getAttributes(SqlFormatterMethod::class)) !== FALSE;
        });
        $keys = array_map(function($method) {
            $formatterMethod = current($method->getAttributes(SqlFormatterMethod::class));
            $name = $formatterMethod->newInstance()->getName();
            if ($name) {
                return $name;
            }
            return $method->getName();
        }, $methods);
        $this->methods = array_combine($keys, array_map(/**
         * @throws ReflectionException
         */ function($method) {
            return $method->getClosure($this);
        }, $methods));
        $this->nameValidator = new ObjectNameValidator();
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function eq($left, $right): string {
        if (is_null($right)) {
            return "{$this->escape($left)} IS NULL";
        }
        return "{$this->escape($left)} = {$this->escape($right)}";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function ne($left, $right): string {
        if (is_null($right)) {
            return "{$this->escape($left)} IS NULL";
        }
        return "{$this->escape($left)} <> {$this->escape($right)}";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function gt($left, $right): string {
        return "{$this->escape($left)} > {$this->escape($right)}";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function lt($left, $right): string {
        return "{$this->escape($left)} < {$this->escape($right)}";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function gte($left, $right): string {
        return "{$this->escape($left)} >= {$this->escape($right)}";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function ge($left, $right): string {
        return "{$this->escape($left)} >= {$this->escape($right)}";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function le($left, $right): string {
        return "{$this->escape($left)} <= {$this->escape($right)}";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function lte($left, $right): string {
        return "{$this->escape($left)} <= {$this->escape($right)}";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function in($left, $right): string {
        if (is_array($right)) {
            $vals = implode(',', array_map(function($val) {
                return $this->escape($val);
            }, $right));
            return "{$this->escape($left)} IN ($vals)";
        }
        return "{$this->escape($left)} IN ({$this->escape($right)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function nin($left, $right): string {
        if (is_array($right)) {
            $vals = implode(',', array_map(function($val) {
                return $this->escape($val);
                }, $right));
            return "{$this->escape($left)} IN ($vals)";
        }
        return "NOT {$this->escape($left)} IN ({$this->escape($right)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod('and')]
    public function logicalAnd(...$args): string {
        if (count($args) < 2) {
            throw new Exception("Missing arguments");
        }
        return "(" . implode(' AND ', array_map(/**
             * @throws Exception
             */ function($val) {
            return $this->escape($val);
            }, $args)) . ")";
    }

    /**
     * @param ...$args
     * @return string
     * @throws Exception
     */
    #[SqlFormatterMethod('or')]
    public function logicalOr(...$args): string {
        if (count($args) < 2) {
            throw new Exception("Missing arguments");
        }
        return "(" . implode(' OR ', array_map(function($val) {
            return $this->escape($val);
            }, $args)) . ")";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function count($arg): string {
        return "COUNT({$this->format($arg)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function min($arg): string {
        return "MIN({$this->escape($arg)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function max($arg): string {
        return "MAX({$this->escape($arg)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function sum($arg): string {
        return "SUM({$this->escape($arg)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function avg($arg): string {
        return "AVG({$this->escape($arg)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function length($arg): string {
        return "LEN({$this->escape($arg)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function startsWith($arg, $search): string {
        $s0 = $this->escape($arg);
        $search->value = '^'.$search->value;
        $s1 = $this->escape($search);
        return "($s0 REGEXP $s1)";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function endsWith($arg, $search): string {
        $s0 = $this->escape($arg);
        $search->value = $search->value.'$';
        $s1 = $this->escape($search);
        return "($s0 REGEXP $s1)";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function trim($arg): string {
        return "TRIM({$this->escape($arg)})";
    }

    #[SqlFormatterMethod]
    public function concat(...$arg): string {
        return "CONCAT(".implode(', ', array_map(/**
             * @throws Exception
             */ function($x) {
                return $this->escape($x);
            }, $arg)).")";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function indexOf($arg0, $search): string {
        return "LOCATE({$this->escape($arg0)}, {$this->escape($search)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function toLower($arg): string {
        return "LOWER({$this->escape($arg)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function toUpper($arg): string {
        return "UPPER({$this->escape($arg)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function contains($arg, $search): string {
        return "({$this->escape($arg)} REGEXP {$this->escape($search)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function day($arg): string {
        return "DAY({$this->escape($arg)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function month($arg): string {
        return "MONTH({$this->escape($arg)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function year($arg): string {
        return "YEAR({$this->escape($arg)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function hour($arg): string {
        return "HOUR({$this->escape($arg)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    function minute($arg): string {
        return "MINUTE({$this->escape($arg)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    function second($arg): string {
        return "SECOND({$this->escape($arg)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function date($arg): string {
        return "DATE({$this->escape($arg)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function floor($arg): string {
        return "FLOOR({$this->escape($arg)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    function ceiling($arg): string {
        return "CEILING({$this->escape($arg)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    function ceil($arg): string {
        return $this->ceiling($arg);
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function round($arg, $decimals = NULL): string {
        if (is_null($decimals)) {
            return "ROUND({$this->escape($arg)}, 0)";
        }
        return "ROUND({$this->escape($arg)}, {$this->escape($decimals)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function bit($arg1, $arg2): string {
        return "({$this->escape($arg1)} & {$this->escape($arg2)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function regex($arg, $pattern): string {
        return "({$this->escape($arg)} REGEXP {$this->escape($pattern)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function text($arg, $pattern): string {
        return "({$this->escape($arg)} REGEXP {$this->escape($pattern)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function substring($arg, $pos, $length = NULL): string {
        if (is_null($length)) {
            return "SUBSTRING({$this->escape($arg)}, {$this->escape($pos)} + 1)";
        }
        return "SUBSTRING({$this->escape($arg)}, {$this->escape($pos)} + 1, {$this->escape($length)})";
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function substr($arg, $pos, $length = NULL): string {
        return $this->substring($arg, $pos, $length);
    }

    /**
     * @throws Exception
     */
    #[SqlFormatterMethod]
    public function getField(string $field): string {
        return $this->escapeName($field);
    }

    /**
     * @param ComparisonExpression $expr
     * @return string
     * @throws Exception
     */
    protected function formatComparison(ComparisonExpression $expr): string {
        $left = $this->format($expr->left);
        $right = $this->format($expr->right);
        if (preg_match('/^null$/i',$right)) {
            switch ($expr->operator) {
                case 'eq':
                    return "$left IS NULL";
                case 'ne':
                    return "NOT $left IS NULL";
            }
        }
        switch ($expr->operator) {
            case 'eq':
                return "($left = $right)";
            case 'ne':
                return "($left <> $right)";
            case 'gt':
                return "($left > $right)";
            case 'lt':
                return "($left < $right)";
            case 'ge':
                return "($left >= $right)";
            case 'le':
                return "($left <= $right)";
            case 'in':
                if (count($right) == 1) {
                    return $this->formatComparison(new ComparisonExpression($left,'eq',$right[0]));
                }
                return "($left IN ($right))";
            case 'nin':
                if (count($right) == 1) {
                    return $this->formatComparison(new ComparisonExpression($left,'ne',$right[0]));
                }
                return "($left IN ($right))";
            default:
                throw new Error('Unsupported comparison operator');
        }
    }

    /**
     * @param ArithmeticExpression $expr
     * @return string
     * @throws Exception
     */
    protected function formatArithmetic(ArithmeticExpression $expr): string {
        $left = $this->format($expr->left);
        $right = $this->format($expr->right);
        switch ($expr->operator) {
            case 'add':
                return "($left + $right)";
            case 'mul':
            case 'multiply':
                return "($left * $right)";
            case 'div':
            case 'divide':
                return "($left / $right)";
            case 'sub':
            case 'subtract':
                return "($left - $right)";
            case 'mod':
            case 'modulo':
                return "($left % $right)";
        }
        throw new Error("Unsupported arithmetic operator");
    }

    /**
     * @throws Exception
     */
    protected function formatLimitSelect($expr): string {
        $sql = $this->formatSelect($expr);
        if (array_key_exists('top', $expr->params) && is_numeric($expr->params['top'])) {
            $top = intval($expr->params['top']);
            if ($top<0) {
                return $sql;
            }
            if (array_key_exists('skip', $expr->params) && is_numeric($expr->params['skip'])) {
                $skip = intval($expr->params['skip']);
                if ($skip<=0) {
                    return "$sql LIMIT $top";
                }
                else {
                    return "$sql LIMIT $top, $skip";
                }
            }
        }
        return $sql;
    }

    /**
     * @throws Exception
     */
    public function escape(mixed $value): string {
        if ($value instanceof DataQueryExpression) {
            return $this->format($value);
        }
        if (is_string($value) && str_starts_with($value, '$')) {
            return $this->escapeName(substr($value, 1));
        }
        
        if (is_array($value) && count($value) == 1) {
            // get first key
            $key = current(array_keys($value));
            if ($key == '$literal') {
                return $this->escape($value);
            }
            // if the key is string and starts with dollar sign
            if (is_string($key) && str_starts_with($key, '$')) {
                // try to find if the given key is a sql dialect
                $method = preg_replace('/^\$/m', '', $key);
                if (array_key_exists($method, $this->methods)) {
                    $val = current(array_values($value));
                    // if arguments is an array
                    if (is_array($val)) {
                        // call dialect method
                        return call_user_func_array($this->methods[$method], $val);
                    }
                    return call_user_func_array($this->methods[$method], array($val));
                }
            }
        }
        // use the magic dollar sign for escaping object names
        if (is_string($value) && str_starts_with($value, '$')) {
            return $this->escapeName(substr($value, 1));
        }
        return DataQueryExpression::escape($value);
    }

    public function getNameValidator(): ObjectNameValidator {
        return $this->nameValidator;
    }

    /**
     * @throws Exception
     */
    public function escapeName(string $name): string {
        return $this->getNameValidator()->escape($name, $this->settings['nameFormat']);
    }

    /**
     * @param QueryExpression $expression
     * @return string
     * @throws Exception
     */
    protected function formatSelect(QueryExpression $expression): string {
        /**
         * @var EntityExpression $entity
         */
        $entity = $expression->params['entity'];
        $from = $this->format($entity);
        $fields = $expression->params['select'];
        $arr = [];
        foreach ($fields as $key => $field) {
            if (is_string($key)) {
                $expr = $this->escape($field);
                $alias = $this->escapeName($key);
                if ($expr == $alias) {
                    $arr[] = $expr;
                    continue;
                }
                $arr[] = $expr . ' AS ' . $alias;
            } else {
                if (is_array($field)) {
                    $arr[] = $this->escape($field);
                } else if ($field instanceof SelectableExpression) {
                    if (isset($field->alias)) {
                        $arr[] = $this->format($field) . ' AS ' . $this->escapeName($field->alias);
                    } else {
                        $arr[] = $this->format($field);
                    }
                }
            }
        }
        $select = implode(', ', $arr);
        if ($expression->params['fixed']) {
            return "SELECT * FROM (SELECT $select) $from";
        }
        //build SQL statement
        //1. select statement
        if ($expression->params['distinct']) {
            $sql = "SELECT DISTINCT $select FROM $from";
        }
        else {
            $sql = "SELECT $select FROM $from";
        }
        //2. where statement
        if ($expression->hasFilter()) {
            $sql .= $this->formatWhere($expression);
        }
        //3. group by statement
        if ($expression->hasGroups()) {
            $sql .= $this->formatGroupBy($expression);
        }
        //4. order by statement
        if ($expression->hasOrders()) {
            $sql .= $this->formatOrderBy($expression);
        }
        return $sql;
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function formatUpdate(QueryExpression $expression): mixed {
        throw new Error("Not implemented");
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function formatInsert(QueryExpression $expression): mixed {
        throw new Error("Not implemented");
    }

    protected function formatOrderBy(mixed $expression): string {
        if (!array_key_exists('orderby', $expression->params)) {
            return '';
        }
        /**
         * @var array $orders
         */
        $orders = $expression->params['orderby'];
        if (count($orders) == 0) {
            return '';
        }
        /**
         * @throws Exception
         */
        $map = function (mixed $order) {
            $field = $this->escape($order['$expr']);
            $direction =  $order['direction'] == -1 ? 'DESC' : 'ASC';
            return (string)$field . ' ' . $direction;
        };
        return ' ORDER BY '.implode(', ', array_map($map, $orders));
    }

    protected function formatGroupBy(mixed $expression): string {
        if (!array_key_exists('groupby', $expression->params)) {
            return '';
        }
        $groups = $expression->params['groupby'];
        if (count($groups) == 0) {
            return '';
        }
        /**
         * @throws Exception
         */
        $map = function (mixed $expr) {
            return $this->escape($expr);
        };
        return ' GROUP BY '.implode(', ', array_map($map, $groups));
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function formatDelete(QueryExpression $expression): mixed {
        throw new Error("Not implemented");
    }

    /**
     * @param QueryExpression $expression
     * @return string
     * @throws Exception
     */
    protected function formatWhere(QueryExpression $expression): string {
        $expr = $expression->get_filter();
        if (is_null($expr)) {
            return '';
        }
        if ($expr instanceof ComparisonExpression) {
            return ' WHERE ' . $this->formatComparison($expr);
        } else if ($expr instanceof LogicalExpression) {
            return ' WHERE ' . $this->formatLogical($expr);
        } else if (is_array($expr)) {
            return ' WHERE ' . $this->escape($expr);
        }
        throw new Error("Invalid filter expression. Expected a logical or comparison expression");
    }

    protected function formatLogical(mixed $expr): string {
        $args = array_map(/**
         * @throws Exception
         */ function($x) {
            return $this->format($x);
        }, $expr->args);
        switch ($expr->operator) {
            case 'or':
                return '('.join(' OR ', $args).')';
            case 'and':
                return '('.join(' AND ', $args).')';
            case 'nor':
                return 'NOT ('.join(' OR ', $args).')';
            case 'not':
                return 'NOT ('.join(' AND ', $args).')';
        }
        throw new Error('Unsupported logical operator');
    }

    /**
     * @param MethodCallExpression $expr
     * @return mixed
     * @throws Exception
     */
    protected function formatMethod(MethodCallExpression $expr): mixed {
        $args = array_map(function($arg) {
           return $this->format($arg);
        }, $expr->args);
        if (array_key_exists($expr->method, $this->methods)) {
            return call_user_func_array($this->methods[$expr->method], $expr->args);
        }
        if (count($args)==0) {
            return $expr->method.'()';
        }
        return $expr->method.'('.implode(', ', $args).')';
    }

    public function resolveEntity(Closure $closure): void {
        $this->entityResolver = $closure;
    }

    public function resolveMember(Closure $closure): void {
        $this->entityResolver = $closure;
    }

    /**
     * @param mixed $expr
     * @return string
     * @throws Exception
     */
    protected function formatMember(mixed $expr): string {

        if (is_null($expr->entity))
                return $this->escapeName($expr->name);
            else
                return $this->formatEntity($expr->entity).'.'.$this->escapeName($expr->name);
    }

    /**
     * @throws Exception
     */
    protected function formatEntity($expr): string {
        if (is_string($expr)) {
            $entity = $expr;
        }
        else {
            $entity = $expr->name;
        }
        return $this->escapeName($entity);
    }

    /**
     * @param mixed $expr
     * @param string $format
     * @return mixed
     * @throws Exception
     */
    public function format($expr, $format = NULL): mixed {
        if ($expr instanceof EntityExpression) {
            return $this->formatEntity($expr);
        }
        else if ($expr instanceof MemberExpression) {
            return $this->formatMember($expr);
        }
        else if ($expr instanceof ComparisonExpression) {
            return $this->formatComparison($expr);
        }
        else if ($expr instanceof LogicalExpression) {
            return $this->formatLogical($expr);
        }
        else if ($expr instanceof QueryExpression) {
            return $this->formatSelect($expr);
        }
        else if ($expr instanceof MethodCallExpression) {
            return $this->formatMethod($expr);
        }
        else if ($expr instanceof LiteralExpression) {
            return $this->escape($expr->value);
        }
        return NULL;
    }

}