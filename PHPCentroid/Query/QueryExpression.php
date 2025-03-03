<?php /** @noinspection DuplicatedCode */

namespace PHPCentroid\Query;
use ReflectionException;
use UnexpectedValueException;
use Closure;

class QueryExpression implements iQueryable
{

    public array $params = array('select' => array(), 'distinct' => FALSE, 'fixed' => FALSE);

    /**
     * @var ?SelectableExpression
     */
    private ?SelectableExpression $__left;
    /**
     * @var ?string
     */
    private ?string $__lop;
    /**
     * @var ?string
     */
    private ?string $__prepared_lop = NULL;
    /**
     * @var ?JoinExpression
     */
    private ?JoinExpression $__join;

    public function __construct($entity = NULL)
    {
        if (!is_null($entity)) {
            $this->from($entity);
        }
    }

    public static function create($entity = NULL): QueryExpression
    {
        return new QueryExpression($entity);
    }

    /**
     * @param string|SelectableExpression|Closure|array ...$args
     * @return $this
     */
    public function select(...$args): iQueryable
    {
        // use closure parser for selecting attributes
        if ($args[0] instanceof Closure) {
            $closure = array_shift($args);
            $parser = new ClosureParser();
            $this->params['select'] = $parser->parseSelect($closure, ...$args);
            return $this;
        }
        $this->params['select'] = array();
        foreach ($args as $arg) {
            $this->selectField($arg);
        }
        return $this;
    }

    private function selectField(mixed $field): void {
        if (is_string($field)) {
            $this->params['select'] += [$field => '$' . $field];
        } else if ($field instanceof SelectableExpression) {
            $alias = $field->alias ?? NULL;
            $this->params['select'] += [$alias => $field];
        } else if (is_array($field)) {
            $key = key($field);
            $value = current($field);
            $this->params['select'] += [$key => $value];
        }
    }

    /**
     * @param string|SelectableExpression $arg,...
     * @return $this
     */
    public function alsoSelect($arg): iQueryable
    {
        $arguments = func_get_args();
        if (!array_key_exists('select', $this->params)) {
            $this->params['select'] = array();
        }
        foreach ($arguments as $argument) {
            $this->selectField($argument);
        }
        return $this;
    }

    public function hasFields(): bool
    {
        if (array_key_exists('select',$this->params)) {
            return count($this->params['select']) > 0;
        }
        return false;
    }

    public function hasFilter(): bool
    {
        return array_key_exists('prepared',$this->params) || array_key_exists('filter',$this->params);
    }

    public function hasOrders(): bool
    {
        return array_key_exists('orderby',$this->params);
    }

    public function hasGroups(): bool
    {
        return array_key_exists('groupby',$this->params);
    }

    public function from($entity): iQueryable
    {
        if (is_string($entity)) {
            $this->params['entity'] = new EntityExpression($entity);
        }
        else if ($entity instanceof EntityExpression) {
            $this->params['entity'] = $entity;
        }
        return $this;
    }

    public function distinct(bool $value = TRUE): iQueryable {
        $this->params['distinct'] = $value;
        return $this;
    }

    public function fixed(bool $value = TRUE): iQueryable {
        $this->params['fixed'] = $value;
        return $this;
    }

    /**
     * @param SelectableExpression|string|Closure ...$args
     * @return $this
     */
    public function groupBy(...$args): iQueryable
    {
        if ($args[0] instanceof Closure) {
            $closure = array_shift($args);
            $parser = new ClosureParser();
            $this->params['groupby'] = $parser->parseSelect($closure, ...$args);
            return $this;
        }
        $this->params['groupby'] = array();
        foreach ($args as $arg) {
            if (is_string($arg)) {
                // append field expression
                $this->params['groupby'][] = [
                    '$getField' => $arg
                ];
            } else {
                $arg->alias = NULL;
                // append field expression
                $this->params['groupby'][] = (array)$arg;
            }
        }
        return $this;
    }

    /**
     * @param SelectableExpression|string|Closure ...$args
     * @return $this
     */
    public function orderBy(mixed ...$args): iQueryable {
        if ($args[0] instanceof Closure) {
            // get closure
            $closure = array_shift($args);
            $parser = new ClosureParser();
            $this->params['orderby'] = $parser->parseSelect($closure, ...$args);
            return $this;
        }
        $this->params['orderby'] = array();
        foreach ($args as $arg) {
            if (is_string($arg)) {
                $this->params['orderby'][] = array(
                    '$expr' => "$$arg",
                    'direction' => 1
                );
            }
            else if ($arg instanceof SelectableExpression) {
                $this->params['orderby'][] = $arg->toArray();
            }
        }
        return $this;
    }

    /**
     * @param SelectableExpression|string $expr,...
     * @return $this
     */
    public function thenBy($expr): iQueryable {
        $arguments = func_get_args();
        foreach ($arguments as $argument) {
            if (is_string($expr)) {
                $this->params['orderby']->append(new MemberExpression($expr));
            }
            else {
                $expr->alias = NULL;
                $expr->order = NULL;
                $this->params['orderby']->append($expr);
            }
        }
        return $this;
    }

    /**
     * @param SelectableExpression|string $expr,...
     * @return $this
     */
    public function orderByDescending($expr): iQueryable
    {
        $arguments = func_get_args();
        $this->params['orderby'] = new MemberListExpression(array());
        foreach ($arguments as $argument) {
            if (is_string($expr)) {
                $this->params['orderby']->append(MemberExpression::create($expr)->orderBy(SelectableExpression::ORDER_DESCENDING));
            }
            else {
                $expr->alias = NULL;
                $expr->orderBy(SelectableExpression::ORDER_DESCENDING);
                $this->params['orderby']->append($expr);
            }
        }
        return $this;
    }

    /**
     * @param SelectableExpression|string $expr,...
     * @return $this
     */
    public function thenByDescending($expr): iQueryable
    {
        $arguments = func_get_args();
        foreach ($arguments as $argument) {
            if (is_string($expr)) {
                $this->params['orderby']->append(MemberExpression::create($expr)->orderBy(SelectableExpression::ORDER_DESCENDING));
            }
            else {
                $expr->alias = NULL;
                $expr->orderBy(SelectableExpression::ORDER_DESCENDING);
                $this->params['orderby']->append($expr);
            }
        }
        return $this;
    }

    /**
     * @param ComparisonExpression $comparison
     */
    private function __append_comparison(ComparisonExpression $comparison): void
    {
        if (array_key_exists('filter', $this->params)) {
            if (is_null($this->__lop)) {
                $this->__lop = LogicalExpression::OPERATOR_AND;
            }
            $expr = $this->params['filter'];
            if ($expr instanceof ComparisonExpression) {
                $this->params['filter'] = new LogicalExpression($this->__lop, array(
                    $expr,
                    $comparison
                ));
            }
            else if ($expr instanceof LogicalExpression) {
                if ($expr->operator === $this->__lop) {
                    $this->params['filter']->args[] = $comparison;
                }
                else {
                    $this->params['filter'] = new LogicalExpression($this->__lop, array(
                        $expr,
                        $comparison
                    ));
                }
            }
        }
        else {
            $this->params['filter'] = $comparison;
        }
    }

    /**
     * @throws ReflectionException
     */
    public function where(mixed $expr, mixed ...$params): iQueryable {
        if ($expr instanceof Closure) {
            $parser = new ClosureParser();
            $this->params['filter'] = $parser->parseFilter($expr, ...$params);
            return $this;
        }
        if (is_string($expr)) {
            $this->__left = new MemberExpression($expr);
        }
        else if ($expr instanceof SelectableExpression) {
            $this->__left = $expr;
        }
        //destroy filter
        if (array_key_exists('filter', $this->params)) {
            unset($this->params['filter']);
        }
        return $this;
    }

    /**
     * @param mixed $arg
     * @return $this
     */
    public function also(mixed $arg): iQueryable {
        $this->__lop = 'and';
        if (is_string($arg)) {
            $this->__left = new MemberExpression($arg);
        }
        else {
            $this->__left = $arg;
        }
        return $this;
    }

    /**
     * @param mixed $arg
     * @return $this
     */
    public function either(mixed $arg): iQueryable
    {
        $this->__lop = 'or';
        if (is_string($arg)) {
            $this->__left = new MemberExpression($arg);
        }
        else {
            $this->__left = $arg;
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function prepare(): QueryExpression
    {
        if (is_null($this->params['filter']))
            return $this;
        if (isset($this->__prepared_lop)) {
            $this->__prepared_lop = LogicalExpression::OPERATOR_AND;
        }
        if (isset($this->params['prepared'])) {
            $prepared = $this->params['prepared'];
            if ($prepared instanceof ComparisonExpression) {
                $this->params['prepared'] = new LogicalExpression($this->__prepared_lop, array(
                    $prepared,
                    $this->params['filter']
                ));
                unset($this->params['filter']);
            }
            else if ($prepared instanceof LogicalExpression) {
                if ($prepared->operator == $this->__prepared_lop) {
                    $prepared->args[] = $this->params['filter'];
                }
                else {
                    $this->params['prepared'] = new LogicalExpression($this->__prepared_lop, array(
                        $prepared,
                        $this->params['filter']
                    ));
                }
                unset($this->params['filter']);
            }
            else {
                throw new UnexpectedValueException('Unsupported prepared expression');
            }
        }
        else {
            $this->params['prepared'] =$this->params['filter'];
            unset($this->params['filter']);
        }
        return $this;
    }

    public function get_filter() {
        if (isset($this->params['prepared'])) {
            if (isset($this->params['filter'])) {
                $lop = $this->__prepared_lop;
                if (is_null($lop)) {
                    $lop = LogicalExpression::OPERATOR_AND;
                }
                return new LogicalExpression($lop, array(
                    $this->params['prepared'],
                    $this->params['filter']
                ));
            }
            else {
                return $this->params['prepared'];
            }
        }
        else if (isset($this->params['filter'])) {
            return $this->params['filter'];
        }
        return NULL;
    }

    /**
     * @param mixed $value
     * @return $this
     */
    public function equal(mixed $value): iQueryable
    {
        $this->__append_comparison(new ComparisonExpression($this->__left,  ComparisonExpression::OPERATOR_EQUAL, $value));
        $this->__left = NULL;
        return $this;
    }

    /**
     * @param mixed $value
     * @return $this
     */
    public function notEqual(mixed $value): iQueryable
    {
        $this->__append_comparison(new ComparisonExpression($this->__left,  ComparisonExpression::OPERATOR_NOT_EQUAL, $value));
        $this->__left = NULL;
        return $this;
    }

    /**
     * @param mixed $value
     * @return $this
     */
    public function lowerThan(mixed $value): iQueryable
    {
        $this->__append_comparison(new ComparisonExpression($this->__left,  ComparisonExpression::OPERATOR_LOWER, $value));
        $this->__left = NULL;
        return $this;
    }

    /**
     * @param mixed $value
     * @return $this
     */
    public function lowerOrEqual(mixed $value): iQueryable
    {
        $this->__append_comparison(new ComparisonExpression($this->__left,  ComparisonExpression::OPERATOR_LOWER_OR_EQUAL, $value));
        $this->__left = NULL;
        return $this;
    }

    /**
     * @param mixed $value
     * @return $this
     */
    public function greaterThan(mixed $value): iQueryable
    {
        $this->__append_comparison(new ComparisonExpression($this->__left,  ComparisonExpression::OPERATOR_GREATER, $value));
        $this->__left = NULL;
        return $this;
    }

    /**
     * @param mixed $value
     * @return $this
     */
    public function greaterOrEqual(mixed $value): iQueryable
    {
        $this->__append_comparison(new ComparisonExpression($this->__left,  ComparisonExpression::OPERATOR_GREATER_OR_EQUAL, $value));
        $this->__left = NULL;
        return $this;
    }

    /**
     * @param string $method
     * @param ?array $add_args
     * @return $this
     */
    private function wrap_left_operand_with_method(string $method, array $add_args = NULL): iQueryable {
        $this->__left = new MethodCallExpression($method,array($this->__left));
        if (is_array($add_args)) {
            foreach ($add_args as $add_arg) {
                $this->__left->args[] = $add_arg;
            }
        }
        return $this;
    }

    public function getDay(): QueryExpression
    {
        return $this->wrap_left_operand_with_method('day');
    }

    public function getMonth(): iQueryable
    {
        return $this->wrap_left_operand_with_method('month');
    }

    public function getYear(): iQueryable
    {
        return $this->wrap_left_operand_with_method('year');
    }

    public function getSeconds(): iQueryable
    {
        return $this->wrap_left_operand_with_method('second');
    }

    public function getMinutes(): QueryExpression
    {
        return $this->wrap_left_operand_with_method('minute');
    }


    public function getHours(): QueryExpression
    {
        return $this->wrap_left_operand_with_method('hour');
    }

    public function getDate(): QueryExpression
    {
        return $this->wrap_left_operand_with_method('date');
    }

    public function toLowerCase(): iQueryable|static
    {
        return $this->wrap_left_operand_with_method('tolower');
    }

    public function toUpperCase(): iQueryable|static
    {
        return $this->wrap_left_operand_with_method('toupper');
    }

    public function floor(): iQueryable
    {
        return $this->wrap_left_operand_with_method('floor');
    }

    public function ceil(): iQueryable
    {
        return $this->wrap_left_operand_with_method('ceiling');
    }

    public function trim(): iQueryable
    {
        return $this->wrap_left_operand_with_method('trim');
    }

    /**
     * @return $this
     */
    public function length(): iQueryable
    {
        return $this->wrap_left_operand_with_method('length');
    }

    /**
     * @param integer $n
     * @return $this
     */
    public function round(int $n = 4): iQueryable
    {
        return $this->wrap_left_operand_with_method('round', array(new LiteralExpression($n)));
    }

    /**
     * @param mixed $x
     * @return $this
     */
    public function add(mixed $x): iQueryable
    {
        return $this->wrap_left_operand_with_method('add', array(new LiteralExpression($x)));
    }

    /**
     * @param mixed $x
     * @return $this
     */
    public function subtract(mixed $x): iQueryable
    {
        return $this->wrap_left_operand_with_method('sub', array(new LiteralExpression($x)));
    }

    /**
     * @param mixed $x
     * @return $this
     */
    public function multiply(mixed $x): iQueryable
    {
        return $this->wrap_left_operand_with_method('mul', array(new LiteralExpression($x)));
    }

    /**
     * @param mixed $x
     * @return $this
     */
    public function divide(mixed $x): iQueryable
    {
        return $this->wrap_left_operand_with_method('div', array(new LiteralExpression($x)));
    }

    /**
     * @param mixed $x
     * @return $this
     */
    public function mod(mixed $x): iQueryable
    {
        return $this->wrap_left_operand_with_method('mod', array(new LiteralExpression($x)));
    }

    /**
     * @param mixed $x
     * @return $this
     */
    public function bit(mixed $x): iQueryable
    {
        return $this->wrap_left_operand_with_method('bit', array(new LiteralExpression($x)));
    }

    /**
     * @param string|EntityExpression $entity
     * @param string $direction
     * @return $this
     */
    public function join(string|EntityExpression $entity, string $direction = 'inner'): iQueryable {
        $this->__join = new JoinExpression($entity,$direction);
        return $this;
    }

    /**
     * @param ComparisonExpression|LogicalExpression $expr
     * @return $this
     */
    public function with(LogicalExpression|ComparisonExpression $expr): iQueryable {
        $this->__join->with($expr);
        if (isset($this->params['expand'])) {
            $this->params['expand'] = array();
        }
        $this->params['expand'][] = $this->__join;
        return $this;
    }


}