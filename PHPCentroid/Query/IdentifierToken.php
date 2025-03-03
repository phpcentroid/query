<?php
/**
 * Created by PhpStorm.
 * User: kbarbounakis
 * Date: 10/10/2016
 * Time: 11:19 μμ
 */

namespace PHPCentroid\Query;


class IdentifierToken extends Token
{
    public $identifier;

    public function __construct($identifier)
    {
        parent::__construct(Token::TOKEN_IDENTIFIER);
        $this->identifier = $identifier;
    }

    public function value_of() {
        return $this->identifier;
    }

}