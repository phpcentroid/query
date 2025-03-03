<?php
/**
 * Created by PhpStorm.
 * User: kbarbounakis
 * Date: 07/10/16
 * Time: 15:41
 */

namespace PHPCentroid\Query;


interface iExpressionFormatter
{
    public function format($expr, $format = NULL);
}