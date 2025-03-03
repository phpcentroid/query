<?php

namespace PHPCentroid\Query;

enum JoinDirectionEnum: string
{
    case INNER = 'inner';
    case LEFT = 'left';
    case RIGHT = 'right';
}