<?php
/**
 * Created by PhpStorm.
 * User: kbarbounakis
 * Date: 10/10/2016
 * Time: 10:41 μμ
 */

namespace PHPCentroid\Query;

abstract class Token
{
    public $syntax;

    const TOKEN_LITERAL = 'Literal';
    const TOKEN_IDENTIFIER ='Identifier';
    const TOKEN_SYNTAX = 'Syntax';
    const TOKEN_TYPE_REGEX = '/(Literal|Identifier|Syntax)/';
    /**
     * @var string
     */
    public $type;

    public function __construct(string $token_type)
    {
        $this->type = $token_type;
    }

    public function is_paren_open(): bool
    {
        return ($this->type==self::TOKEN_SYNTAX) && ($this->syntax=='(');
    }

    public function is_paren_close(): bool
    {
        return ($this->type==self::TOKEN_SYNTAX) && ($this->syntax==')');
    }

    public function is_slash(): bool
    {
        return ($this->type==self::TOKEN_SYNTAX) && ($this->syntax=='/');
    }

    public function is_alias(): bool
    {
        return ($this->type==self::TOKEN_IDENTIFIER) && ($this->value_of()=='as');
    }

    public function is_order(): bool
    {
        return ($this->type==self::TOKEN_IDENTIFIER) && (($this->value_of()=='asc') || ($this->value_of()=='desc'));
    }

    public function is_comma(): bool
    {
        return ($this->type==self::TOKEN_SYNTAX) && ($this->syntax==',');
    }

    public function is_negative(): bool
    {
        return ($this->type==self::TOKEN_SYNTAX) && ($this->syntax=='-');
    }

    abstract function value_of();

}