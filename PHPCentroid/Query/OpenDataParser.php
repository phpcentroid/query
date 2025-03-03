<?php

namespace PHPCentroid\Query;
use Closure;
use DateTime;
use Error;

class OpenDataParser
{
    const GUID_REGEX = '/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$/';
    const ARITHMETIC_OPERATOR_REGEX = '/^(add|sub|mul|div|mod)$/';
    const DURATION_REGEX = '/^(-)?P([0-9]+Y)?([0-9]+M)?([0-9]+D)?(T)?([0-9]+H)?([0-9]+M)?([0-9]+S)?$/';
    const LOGICAL_OPERATOR_REGEX = '/^(or|nor|not|and)$/';
    const DATETIME_REGEX = '/(\d{4})(?:-?W(\d+)(?:-?(\d+)D?)?|(?:-(\d+))?-(\d+))(?:[T ](\d{2}):(\d{2})(?::(\d{2})(?:\.(\d{2}))?)?)?(?:Z(-?\d*))?([+-](\d{2}):?(\d{2}))?$/';
    const COMPARISON_OPERATOR_REGEX = '/^(eq|ne|le|lt|ge|gt|in|nin)$/';

    const REGEXP_CHAR = '/[a-zA-Z]/';
    const REGEXP_DIGIT = '/[0-9]/';
    const CHR_WHITESPACE = ' ';
    const CHR_UNDERSCORE = '_';
    const CHR_DOLLARSIGN = '$';
    const CHR_POINT = '.';

    private $member_resolver_closure = NULL;

    private $method_resolver_closure = NULL;

    /**
     * @var int
     */
    private $current = 0;
    /**
     * @var int
     */
    private $offset = 0;
    /**
     * @var string
     */
    private $source = NULL;
    /**
     * @var array
     */
    private $tokens = array();

    /**
     * @var array
     * @noinspection PhpPropertyOnlyWrittenInspection
     */
    private $options = array();

    private static function is_char($chr) {
        if (is_null($chr)) {
            return false;
        }
        return preg_match(self::REGEXP_CHAR,$chr);
    }

    private static function is_digit($chr) {
        if (is_null($chr)) {
            return false;
        }
        return preg_match(self::REGEXP_DIGIT,$chr);
    }

    private static function is_whitespace($chr): bool {
        if (is_null($chr)) {
            return false;
        }
        return ($chr == self::CHR_WHITESPACE);
    }


    private static function is_identifier_start_char($chr): bool {
        if (is_null($chr)) {
            return false;
        }
        return ($chr == self::CHR_UNDERSCORE) || ($chr ==self::CHR_DOLLARSIGN) || self::is_char($chr);
    }

    private static function is_identifier_char($chr): bool {
        if (is_null($chr)) {
            return false;
        }
        return self::is_identifier_start_char($chr) || self::is_digit($chr);
    }

    public function __construct()
    {
        $this->member_resolver_closure = function ($member) {
            return $member;
        };
        $this->method_resolver_closure = function ($method, $args = NULL) {
            return NULL;
        };
    }

    /**
     * @return Token
     */
    private function get_next_token(): ?Token {
        return ($this->offset < (count($this->tokens) - 1)) ? $this->tokens[$this->offset+1] : NULL;
    }

    /**
     * @return Token
     */
    private function get_current_token(): ?Token {
        return ($this->offset < count($this->tokens)) ? $this->tokens[$this->offset] : NULL;
    }
    /**
     * @return Token
     */
    private function get_previous_token(): ?Token {
        return (($this->offset > 0) && (count($this->tokens) > 0)) ? $this->tokens[$this->offset - 1] : NULL;
    }


    /**
     * @param ?Token $token
     * @return string
     */
    private function get_operator(?Token $token): ?string {
        if ($token instanceof IdentifierToken) {
            switch ($token->identifier)
            {
            case "and": return 'and';
            case "or": return 'or';
            case "eq": return 'eq';
            case "ne": return 'ne';
            case "lt": return 'lt';
            case "le": return 'le';
            case "gt": return 'gt';
            case "ge": return 'ge';
            case "in": return 'in';
            case "nin": return 'nin';
            case "add": return 'add';
            case "sub": return 'sub';
            case "mul": return 'mul';
            case "div": return 'div';
            case "mod": return 'mod';
            case "not": return 'not';
        }
        }
        return NULL;
    }

    /**
     * @return bool
     */
    private function at_end(): bool {
        return $this->offset>=count($this->tokens);
    }

    private function move_next(): void
    {
        $this->offset++;
    }

    /**
     * @param ?Token $token
     * @throws Error
     */
    private function expect(?Token $token): void
    {
        if ($this->get_current_token()->value_of() != $token->value_of())
            throw new Error('Expected '.$token->value_of());
        $this->move_next();
    }

    private function  expect_any() {
        if ($this->at_end()) {
            throw new Error('Unexpected end');
        }
    }

    /**
     * @param int $current
     * @return mixed
     */
    private function  skip_digits(int &$current) {
        $source = $this->source;
        if (!self::is_digit($source[$current])) {
            return NULL;
        }
        $current++;
        while (($current < strlen($source)) && (self::is_digit($source[$current]))) {
            $current++;
        }
        return $current;
    }

    /**
     * @return ?SyntaxToken
     * @throws Error
     */
    private function  parse_syntax(): ?SyntaxToken {
        $token = NULL;
        switch ($this->source[$this->current]) {
            case '(': $token = SyntaxToken::ParenOpen(); break;
            case ')': $token = SyntaxToken::ParenClose(); break;
            case '/': $token = SyntaxToken::Slash(); break;
            case ',': $token = SyntaxToken::Comma(); break;
            default: throw new Error('Unknown token');
        }
        $this->offset = $this->current + 1;
        return $token;
    }

    /**
     * @param ?string $value
     * @return LiteralToken
     * @throws Error
     */
    private function  parse_guid_string(?string $value): LiteralToken {

        $offset = $this->offset;
        if (!is_string($value)) {
            throw new Error("Invalid argument at $offset.");
        }
        if (!preg_match(self::GUID_REGEX, $value)) {
            throw new Error("Guid format is invalid at $offset.");
        }
        return new LiteralToken($value, LiteralToken::Guid);
    }

    /**
     * @param bool $minus
     * @return IdentifierToken|Token
     */
    private function  parse_identifier(bool $minus = FALSE) {
        $source = $this->source;
        $offset = $this->offset;
        $current = $this->current;
        $current++;
        while ($current<strlen($source)) {
            if (!self::is_identifier_char($source[$current])) {
                break;
            }
            $current++;
        }
        $name = trim(substr($source, $offset, $current - $offset));
        $last_offset = $offset;
        $offset = $current;
        switch ($name) {
            case 'INF':
                $this->current = $current;$this->offset=$offset;
                return LiteralToken::PositiveInfinity();
            case '-INF':
                $this->current = $current;$this->offset=$offset;
                return LiteralToken::NegativeInfinity();
            case 'NaN':
                $this->current = $current;$this->offset=$offset;
                return LiteralToken::NaN();
            case 'true':
                $this->current = $current;$this->offset=$offset;
                return LiteralToken::True();
            case 'false':
                $this->current = $current;$this->offset=$offset;
                return LiteralToken::False();
            case 'null':
                $this->current = $current;$this->offset=$offset;
                return LiteralToken::Null();
            case '-':
                $this->current = $current;$this->offset=$offset;
                return LiteralToken::Negative();
            default:
                if ($minus) {
                    $offset = $last_offset + 1;
                    $this->current = $current;$this->offset=$offset;
                    return SyntaxToken::Negative();
                }
                $this->current = $current;$this->offset=$offset;
                break;
        }
        if (($offset < strlen($source) && ($source[$offset] == '\''))) {
            switch ($name) {
                case 'X':
                case 'binary':
                    $string_type = LiteralToken::Binary;
                    break;
                case 'datetime':
                    $string_type = LiteralToken::DateTime;
                    break;
                case 'guid':
                    $string_type = LiteralToken::Guid;
                    break;
                case 'time':
                    $string_type = LiteralToken::Time;
                    break;
                case 'datetimeoffset':
                    $string_type = LiteralToken::DateTimeOffset;
                    break;
                default:
                    $string_type = LiteralToken::None;
                    break;
            }
            if (($string_type != LiteralToken::None) && ($source[$offset] == '\'')) {
                $content = $this->parse_string();
                return $this->parse_special_string($content->value_of(), $string_type);
            }
        }
        return new IdentifierToken($name);
    }

    /**
     * @returns Token
     * @throws Error
     */
    private function parse_string() {
        $hadEnd = false;
        $source = $this->source;
        $offset = $this->offset;
        $current = $this->current;
        $current++;
        $sb = '';
        while ($current<strlen($source)) {
            $c = $source[$current];
            if ($c == '\'') {
                if (($current < (strlen($source) - 1)) && ($source[$current+1] == '\'')) {
                    $current++;
                    $sb .= '\'';
                }
                else {
                    $hadEnd = true;
                    break;
                }
            }
            else {
                $sb .= $c;
            }
            $current++;
        }
        if (!$hadEnd)
        {
            throw new Error("Unterminated string starting at $offset");
        }
        $this->current = $current;
        $this->offset = $current + 1;
        return new LiteralToken($sb, LiteralToken::String);
    }

    /**
     * @param string $value
     * @throws Error
     * @returns Token
     */
    private function parse_binary($value) {
        throw new Error('Not yet implemented');
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function parse_datetime_string(?string $value): ?LiteralToken
    {
        if (is_string($value)) {
            $matches = array();
            if (preg_match(self::DATETIME_REGEX, $value, $matches)) {
                return new LiteralToken(new DateTime($value), LiteralToken::DateTime);
            }
            else {
                $offset = $this->offset;
                throw new Error("Datetime format is invalid at $offset.");
            }
        }
        return NULL;
    }

    /**
     * @param string $value
     * @returns Token
     */
    private function parse_datetime_offset_string($value): Token {
        return $this->parse_datetime_string($value);
    }

    /**
     * @param ?string $value
     * @return Token|null
     */
    private function parse_time_string(?string $value): ?Token {
        if (is_string($value)) {
            $matches = array();
            if (preg_match(self::DURATION_REGEX, $value, $matches)) {
                return new LiteralToken($value, LiteralToken::Time);
            }
            else {
                $offset = $this->offset;
                throw new Error("Datetime format is invalid at $offset.");
            }
        }
        return NULL;
    }


    /**
     * @param $value
     * @param $string_type
     * @return ?Token
     */
    private function parse_special_string($value, $string_type): ?Token {
        if ($string_type == LiteralToken::Binary) {
            return $this->parse_binary($value);
        }
        else if ($string_type == LiteralToken::Guid) {
            return $this->parse_guid_string($value);
        }
        else if ($string_type == LiteralToken::DateTime) {
            return $this->parse_datetime_string($value);
        }
        else if ($string_type == LiteralToken::DateTimeOffset) {
            return $this->parse_datetime_offset_string($value);
        }
        else if ($string_type == LiteralToken::Time) {
            return $this->parse_time_string($value);
        }

        throw new Error('String type was out of range.');
    }

    /**
     * @param Closure $closure
     */
    public function resolve_member($closure) {
        $this->member_resolver_closure = $closure;
    }

    /**
     * @param Closure $closure
     */
    public function resolve_method($closure) {
        $this->method_resolver_closure = $closure;
    }

    private function create_expression($left, $operator, $right) {

        if (preg_match(self::LOGICAL_OPERATOR_REGEX, $operator)) {
            if ($left instanceof LogicalExpression) {
                if ($left->operator==$operator) {
                    $expr = new LogicalExpression($operator, array());
                    foreach ($left->args as $arg) {
                        $expr->args[] = $arg;
                    }
                    $expr->args[] = $right;
                    return $expr;
                }
            }
            return  new LogicalExpression($operator, array($left, $right));
        }
        else if (preg_match(self::ARITHMETIC_OPERATOR_REGEX, $operator)) {
            return new ArithmeticExpression($left, $operator, $right);
        }
        else if (($left instanceof ArithmeticExpression) ||
            ($left instanceof MethodCallExpression) ||
            ($left instanceof MemberExpression)
        ) {
            return new ComparisonExpression($left, $operator, $right);
        }
        else if (preg_match(self::COMPARISON_OPERATOR_REGEX, $operator)) {
            return new ComparisonExpression($left, $operator, $right);
        }
        else {
            throw new Error('Invalid or unsupported expression arguments.');
        }

    }

    private function get_next() {
        $current = $this->current; $source = $this->source; $offset = $this->offset;
        if ($offset>=strlen($source)) {
            return NULL;
        }
        $length = strlen($source);
        while(($offset<$length) && (OpenDataParser::is_whitespace($source[$offset]))) {
            $offset++;
        }
        if ($offset>=$length) {
            return NULL;
        }
        $current = $offset;
        $this->current = $current;
        $c = $source[$current];
        switch ($c) {
            case '-':
                return $this->parse_sign();
            case '\'':
                return $this->parse_string();
            case '(':
            case ')':
            case ',':
            case '/':
                return $this->parse_syntax();
            default:
                if (self::is_digit($c))
                {
                    return $this->parse_numeric();
                }
                else if (self::is_identifier_start_char($c))
                {
                    return $this->parse_identifier(false);
                }
                else
                {
                    throw new Error("Unexpected character '$c' at offset $current.");
                }
        }

    }

    private function reset_offset() {
        $this->current = 0;
        $this->offset = 0;
    }

    private function to_list() {

        $this->reset_offset();
        $result = array();
        $token = $this->get_next();
        while(!is_null($token)) {
            $result[] = $token;
            $token = $this->get_next();
        }
        return $result;
    }

    /**
     * @param string $str
     * @param array $options
     * @return MemberListExpression|DataQueryExpression
     * @throws Error
     */
    public function parse($str, $options = array()) {
        if (!is_string($str)) {
            return NULL;
        }
        $this->reset_offset();
        $this->options = $options;
        $this->source = $str;
        $this->tokens = $this->to_list();
        $this->reset_offset();
        $result = $this->parse_common();
        if ($result instanceof SelectableExpression) {
            if ($this->at_end())
                return  new MemberListExpression(array($result));
            return $this->parse_collection(array($result));

//                $collection = new MemberListExpression(array($result));
//                if ($this->get_current_token()->is_comma()) {
//                    $this->move_next();
//                    $expr = $this->parse_common();
//                }
//                else {
//                    throw new \Error('Expected member');
//                }
//                while ($expr instanceof MemberExpression) {
//                    $collection->append($expr);
//                    $expr = NULL;
//                    if ($this->get_current_token() && $this->get_current_token()->is_comma()) {
//                        $this->move_next();
//                        $expr = $this->parse_common();
//                    }
//                }
//                if (!$this->at_end()) {
//                    throw new \Error('Expected member');
//                }
//                return $collection;
//            }
        }
        return $result;
    }

    private function parse_collection($input = array()) {
        $collection = new MemberListExpression($input);
        if ($this->at_end()) {
            return $collection;
        }
        if ($this->get_current_token()->is_comma()) {
            $this->move_next();
            $expr = $this->parse_common();
            while ($expr instanceof SelectableExpression) {
                $collection->append($expr);
                $expr = NULL;
                if ($this->get_current_token() && $this->get_current_token()->is_comma()) {
                    $this->move_next();
                    $expr = $this->parse_common();
                }
            }
            if (!$this->at_end()) {
                throw new Error('Expected member');
            }
            return $collection;
        }
        else {
            throw new Error('Expected member');
        }
    }

    private function parse_common() {
        if (count($this->tokens) == 0) {
            return NULL;
        }
        $result = $this->parse_common_item();
        if ($this->at_end()) {
            return $result;
        }
        else if ($this->get_current_token()->is_comma() ||
            $this->get_current_token()->is_paren_close()) {
            return $result;
        }
        else if ($this->get_current_token()->is_alias() && ($result instanceof SelectableExpression)) {
            $this->move_next();
            $result->alias = $this->get_current_token()->value_of();
            $this->move_next();
            return $result;
        }
        else if ($this->get_current_token()->is_order() && ($result instanceof SelectableExpression)) {
            $result->order = $this->get_current_token()->value_of();
            $this->move_next();
            return $result;
        }
        else {
            $operator = $this->get_operator($this->get_current_token());
            $this->move_next();
            $right = $this->parse_common_item();
            $expr = $this->create_expression($result, $operator, $right);
            if (!$this->at_end() && preg_match(self::LOGICAL_OPERATOR_REGEX, $this->get_operator($this->get_current_token()))) {
                $operator = $this->get_operator($this->get_current_token());
                $this->move_next();
                $right_expr = $this->parse_common();
                return $this->create_expression($expr, $operator, $right_expr);
            }
            return $expr;

        }
    }

    private function parse_common_item() {
        if (count($this->tokens) == 0) {
            return NULL;
        }
        switch ($this->get_current_token()->type) {
            case Token::TOKEN_IDENTIFIER:
                if ($this->get_next_token() && ($this->get_next_token()->is_paren_open()) && is_null($this->get_operator($this->get_current_token()))) {
                    return $this->parse_method_call();
                }
                else if ($this->get_operator($this->get_current_token()) == '$not') {
                    throw new Error('Not operator is not yet implemented.');
                }
                else {
                    $member = $this->parse_member();
                    if(!$this->at_end() && $this->get_current_token()->is_slash()) {
                        throw new Error('Slash syntax is not yet implemented.');
                    }
                    $this->move_next();
                    return $member;
                }
            case Token::TOKEN_LITERAL:
                $value = $this->get_current_token()->value_of();
                $this->move_next();
                return $value;
            case Token::TOKEN_SYNTAX:
                if ($this->get_current_token()->is_negative()) {
                    throw new Error('Negative syntax is not yet implemented.');
                }

                if ($this->get_current_token()->is_paren_open()) {
                    $this->move_next();
                    $result = $this->parse_common();
                    $this->expect(SyntaxToken::ParenClose());
                    return $result;
                }
                else {
                    throw new Error('Expected syntax.');
                }
            default:
                return NULL;
        }
    }
    /**
     * @return Token
     * @throws Error
     */
    private function parse_numeric() {
        $current = $this->current; $source = $this->source; $offset = $this->offset;
        $floating = false;
        $current++;
        while($current<strlen($source)) {
            $c = $source[$current];
            if ($c == self::CHR_POINT) {
                if ($floating) {
                    break;
                }
                $floating = true;
            }
            else if (!self::is_digit($c)) {
                break;
            }
            $current++;
        }
        $haveExponent = false;
        if ($current<strlen($source)) {
            $c = $source[$current];
            if ($c == 'E' || $c == 'e') {
                $current++;
                if ($source[$current] == '-') {
                    $current++;
                }
                $exponentEnd = ($current == strlen($source)) ? NULL : $this->skip_digits($current);
                if (is_null($exponentEnd)) {
                    throw new Error("Expected digits after exponent at $offset.");
                }
                $current = $exponentEnd;
                $haveExponent = true;
                if ($current<strlen($source)) {
                    $c = $source[$current];
                    if ($c == 'm' || $c == 'M')
                        throw new Error("Unexpected exponent for decimal literal at $offset.");
                    else if ($c == 'l' || $c == 'L')
                        throw new Error("Unexpected exponent for long literal at $offset.");

                }
            }
        }
        $text = substr($source,$offset, $current - $offset);
        $value = NULL;
        $type = NULL;
        if ($current<strlen($source)) {
            $c = $source[$current];
            switch ($c) {
                case 'F':
                case 'f':
                    $value = floatval($text); $type = LiteralToken::Single; $current++;
                    break;
                case 'D':
                case 'd':
                    $value = floatval($text); $type = LiteralToken::Double; $current++;
                    break;
                case 'M':
                case 'm':
                    $value = floatval($text); $type = LiteralToken::Decimal; $current++;
                    break;
                case 'L':
                case 'l':
                    $value = intval($text); $type = LiteralToken::Long; $current++;
                    break;
                default:
                    if ($floating || $haveExponent) {
                        $value = floatval($text); $type = LiteralToken::Double;
                    }
                    else {
                        $value = intval($text); $type = LiteralToken::Int;
                    }
                    break;
            }

        }
        else
        {
            if ($floating || $haveExponent) {
                $value = floatval($text); $type = LiteralToken::Double;
            }
            else {
                $value = intval($text); $type = LiteralToken::Int;
            }
        }

        $offset = $current;
        $this->offset = $offset;
        $this->current = $current;
        return new LiteralToken($value, $type);

    }

    /**
     * @return Token
     */
    private function parse_sign() {
        $this->current++;
        if (self::is_digit($this->source[$this->current])) {
            return $this->parse_numeric();
        }
        else {
            return $this->parse_identifier(true);
        }
    }

    private function parse_member() {
        if (count($this->tokens) == 0) {
            return NULL;
        }
        $identifier = $this->get_current_token()->value_of();
        while ($this->get_next_token() && $this->get_next_token()->is_slash()) {
            $this->move_next();
            $this->move_next();
            $identifier .= '/'.$this->get_current_token()->value_of();
        }
        $alias = NULL;
        if ($this->get_next_token() && $this->get_next_token()->is_alias()) {
            $this->move_next();
            $this->move_next();
            $alias = $this->get_current_token()->value_of();
        }
        $order = NULL;
        if ($this->get_next_token() && $this->get_next_token()->is_order()) {
            $this->move_next();
            $order = $this->get_current_token()->value_of();
        }
        $member = $this->member_resolver_closure->call($this, $identifier);
        $result = MemberExpression::create($member);
        $result->alias = $alias;
        $result->order = $order;
        return $result;
    }

    private function parse_method_call_arguments(&$args = array()) {
        if ($this->get_current_token()->is_comma()) {
            $this->move_next();
            $this->expect_any();
            $this->parse_method_call_arguments($args);
        }
        else if ($this->get_current_token()->is_paren_close()) {
            $this->move_next();
        }
        else {
            $arg = $this->parse_common();
            $args[] = $arg;
            $this->parse_method_call_arguments($args);
        }
    }

    private function parse_method_call() {
        if (count($this->tokens) == 0) {
            return NULL;
        }
        $method = $this->get_current_token()->value_of();
        $this->move_next();
        $this->expect(SyntaxToken::ParenOpen());
        $args = array();
        $this->parse_method_call_arguments($args);
        $expr = $this->method_resolver_closure->call($this, $method, $args);
        if (is_null($expr)) {
            return new MethodCallExpression($method, $args);
        }
        else {
            return $expr;
        }
    }




}