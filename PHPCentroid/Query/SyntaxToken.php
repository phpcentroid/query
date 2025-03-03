<?php
/**
 * Created by PhpStorm.
 * User: kbarbounakis
 * Date: 10/10/2016
 * Time: 11:30 μμ
 */

namespace PHPCentroid\Query;


class SyntaxToken extends Token
{
    public $syntax;

    public function __construct($chr)
    {
        parent::__construct(Token::TOKEN_SYNTAX);
        $this->syntax = $chr;
    }

    public static function ParenOpen(): SyntaxToken
    {
        return new SyntaxToken('(');
    }

    public static function ParenClose(): SyntaxToken
    {
        return new SyntaxToken(')');
    }

    public static function Slash(): SyntaxToken
    {
        return new SyntaxToken('/');
    }

    public static function Comma(): SyntaxToken
    {
        return new SyntaxToken(',');
    }

    public static function Negative(): SyntaxToken
    {
        return new SyntaxToken('-');
    }

    public function value_of() {
        return $this->syntax;
    }

}