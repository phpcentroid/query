<?php

namespace PHPCentroid\Query;

class ResolvingMethod {
    /**
     * 
     * @param ClosureParser $target
     * @param mixed $member
     */
    public function __construct(public mixed $target, public mixed $method)
    {
        
    }
}