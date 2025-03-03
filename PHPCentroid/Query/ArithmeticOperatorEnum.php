<?php

namespace PHPCentroid\Query;

enum ArithmeticOperatorEnum: string {
    case ADD = 'add';
    case SUBTRACT = 'sub';
    case MULTIPLY = 'mul';
    case DIVIDE = 'div';
    case MODULO = 'mod';

}