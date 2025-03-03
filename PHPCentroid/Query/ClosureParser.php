<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

/** @noinspection PhpUnusedAliasInspection */

namespace PHPCentroid\Query;

use Closure;
use Exception;
use Error;
use League\Event\EventDispatcher;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Opis\Closure\SerializableClosure;
use Opis\Closure\ReflectionClosure;
use ReflectionException;


class ClosureParser {

    private Parser $parser;
    private array $params = array();
    protected EventDispatcher $dispatcher;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->dispatcher = new EventDispatcher();
    }

    /**
     * @throws ReflectionException
     */
    protected function getClosure(Closure $closure): Expr\Closure {
        $reflector = new ReflectionClosure($closure);
        $code = '<?php $body = ' . $reflector->getCode() . ';';
        // and parse
        $ast = $this->parser->parse($code);
        $expr = $ast[0];
        if ($expr instanceof Expression) {
            $stmt = $expr->expr;
            if ($stmt instanceof Assign) {
                $stmt = $stmt->expr;
            }
            if ($stmt instanceof Expr\Closure) {
                return $stmt;
            }
        }
        throw new ReflectionException('Invalid closure format');
    }

    protected function getParams(Expr\Closure $closure, array $values): array {
        $params = $closure->params;
        $arr = array();
        foreach ($params as $index => $param) {
            if ($index > 0) {
                $arr += [ $param->var->name => $values[$index - 1] ];
            }
        }
        return $arr;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function parseFilter(Closure $closure,mixed ...$params): array {
        try {
            $closureExpr = $this->getClosure($closure);
            $this->params = $this->getParams($closureExpr, $params);
            $stmts = $closureExpr->getStmts();
            $stmt = current($stmts);
            if ($stmt instanceof Stmt\Return_) {
                $expr = $stmt->expr;
                if ($expr instanceof BinaryOp) {
                    return $this->parseCommon($expr);
                }
            }
        } finally {
            // clear params
            $this->params = array();
        }
        throw new Exception('Invalid where closure. Expected a closure which returns a binary expression.');
    }

    /**
     * @param Closure $closure
     * @param mixed ...$params
     * @return array
     */
    public function parseSelect(Closure $closure,mixed ...$params): array
    {
        try {
            $closureExpr = $this->getClosure($closure);
            $this->params = $this->getParams($closureExpr, $params);
            $arr = array();
            $stmts = $closureExpr->getStmts();
            $stmt = current($stmts);
            if ($stmt instanceof Stmt\Return_) {
                $expr = $stmt->expr;
                if ($expr instanceof Array_) {
                    foreach ($expr->items as $item) {
                        if ($item->key instanceof String_) {
                            $arr[$item->key->value] = $this->parseCommon($item->value);
                        } else {
                            $arr[] = $this->parseCommon($item->value);
                        }
                    }
                } else if ($expr instanceof PropertyFetch) {
                    $arr[$expr->name->name] = $this->parseCommon($expr);
                }
            }
            return $arr;
        } finally {
            $this->params = array();
        }
    }

    /**
     * @throws Exception
     */
    public function parseMember(PropertyFetch $member): array
    {
        if ($member->var instanceof PropertyFetch) {
            $qualified = array($member->name->name);
            $var = $member->var;
            while($var instanceof PropertyFetch) {
                array_unshift($qualified, $var->name->name);
                if ($var->var instanceof PropertyFetch) {
                    $var = $var->var;
                } else {
                    $var = null;
                }
            }
            if (count($qualified) == 1) {
                $member = $qualified[0];
                $event = new ResolvingMember($this, $member);
                $this->dispatcher->dispatch($event);
                if (is_array($event->member)) {
                    return $event->member;
                }
                return [ '$getField' => $event->member ];
            }
            // get member
            $member = implode('.', array_slice($qualified, -2));
            // get fully qualified member
            $fullyQualifiedMember = implode('.', $qualified);
            // dispatch event
            $event = new ResolvingJoinMember($this, $member, fullyQualifiedMember: $fullyQualifiedMember);
            $this->dispatcher->dispatch($event);
            // member should be an instance of selectable expression
            if (is_array($event->member)) {
                return $event->member;
            }
            // or a string
            // split member
            $member = explode('.', $event->member);
            // for creating a member expression
            if (count($member) == 1) {
                return [ '$getField' => $member[0] ];
            }
            // with alias
            return array('$getField' => implode(',', $member));
        } else if ($member->var instanceof Variable) {
            return array('$getField' => $member->name->name);
        }
        throw new Exception('Invalid member expression');
    }

    /**
     * @throws Exception
     */
    public function parseLiteral(Scalar $expr): mixed {
        if (property_exists($expr, 'value')) {
            return $expr->value;
        }
        throw new Exception('Unsupported scalar expression');
    }

    /**
     * @throws Exception
     */
    public function parseMethodCall(Expr\FuncCall $expr): array {
        $args = array_map(function(Arg $arg) {
            return $this->parseCommon($arg->value);
        }, $expr->args);
        $name = $expr->name->name;
        $event = new ResolvingMethod($this, $name);
        $this->dispatcher->dispatch($event);
        if (is_array($event->method)) {
            return $event->method;
        }
        $escaped = '$' . $name;
        return array($escaped => $args);
    }

    /**
     * @throws Exception
     */
    public function parseBinary(BinaryOp $expr): array {
        $binaryOperator = $expr->getOperatorSigil();
        $left = $this->parseCommon($expr->left);
        $right = $this->parseCommon($expr->right);
        /** @noinspection PhpSwitchCanBeReplacedWithMatchExpressionInspection */
        switch ($binaryOperator) {
            case '==':
            case '===':
                return array('$eq' => array($left, $right));
            case '!=':
                return array('$ne' => array($left, $right));
            case '>':
                return array('$gt' => array($left, $right));
            case '<':
                return array('$lt' => array($left, $right));
            case '>=':
                return array('$ge' => array($left, $right));
            case '<=':
                return array('$le' => array($left, $right));
            case '&&':
                return array('$and' => array($left, $right));
            case '||':
                return array('$or' => array($left, $right));
            case '+':
                return array('$add' => array($left, $right));
            case '-':
                return array('$subtract' => array($left, $right));
            case '*':
                return array('$multiply' => array($left, $right));
            case '/':
                return array('$divide' => array($left, $right));
            case '%':
                return array('$bit' => array($left, $right));
            default:
                throw new Error("Unsupported operator " . $binaryOperator);
        }
    }

    /**
     * @throws Exception
     */
    public function parseVariable(Variable $expr): mixed
    {
        if (!array_key_exists($expr->name, $this->params)) {
            throw new Exception('The variable ' . $expr->name . ' is not defined');
        }
        return $this->params[$expr->name];
    }

    /**
     * @param Expr $expr
     * @return DataQueryExpression
     * @throws Exception
     */
    public function parseCommon(Expr $expr): mixed {
        if ($expr instanceof PropertyFetch) {
            return $this->parseMember($expr);
        } else if ($expr instanceof BinaryOp) {
            return $this->parseBinary($expr);
        } else if ($expr instanceof Scalar) {
            return $this->parseLiteral($expr);
        } else if ($expr instanceof Expr\FuncCall) {
            return $this->parseMethodCall($expr);
        } else if ($expr instanceof Variable) {
            return $this->parseVariable($expr);
        }
        throw new Exception("An expression of type " . get_class($expr) . " is not supported");
    }

}