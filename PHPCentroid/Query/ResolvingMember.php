<?php

namespace PHPCentroid\Query;

class ResolvingMember {
    /**
     * 
     * @param ClosureParser $target
     * @param mixed $member
     */
    public function __construct(public mixed $target, public mixed $member)
    {
        
    }
}