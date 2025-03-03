<?php
/**
 * Created by PhpStorm.
 * User: kbarbounakis
 * Date: 10/10/2016
 * Time: 11:01 μμ
 */

namespace PHPCentroid\Query;


class LiteralToken extends Token
{

    const Null = 'Null';
    const String = 'String';
    const Boolean = 'Boolean';
    const Single = 'Single';
    const Double = 'Double';
    const Decimal = 'Decimal';
    const Int = 'Int';
    const Long = 'Long';
    const Binary = 'Binary';
    const DateTime = 'DateTime';
    const DateTimeOffset = 'DateTimeOffset';
    const None = 'None';
    const Time = 'Time';
    const Guid = 'Guid';
    const Duration ='Duration';

    public $literal_type;

    public $value;


    public function __construct($value, $literal_type)
    {
        parent::__construct(Token::TOKEN_LITERAL);
        $this->value=$value;
        $this->literal_type=$literal_type;
    }

    public static function PositiveInfinity(): LiteralToken
    {
        return new LiteralToken(NAN, LiteralToken::Double);
    }

    public static function NegativeInfinity(): LiteralToken
    {
        return new LiteralToken(NAN, LiteralToken::Double);
    }

    public static function NaN(): LiteralToken
    {
        return new LiteralToken(NAN, LiteralToken::Double);
    }

    public static function True(): LiteralToken
    {
        return new LiteralToken(TRUE, LiteralToken::Boolean);
    }

    public static function False(): LiteralToken
    {
        return new LiteralToken(FALSE, LiteralToken::Boolean);
    }

    public static function Null(): LiteralToken
    {
        return new LiteralToken(NULL, LiteralToken::Null);
    }

    public static function Negative(): LiteralToken
    {
        return new LiteralToken('-', LiteralToken::String);
    }

    public function value_of() {
        return $this->value;
    }


}