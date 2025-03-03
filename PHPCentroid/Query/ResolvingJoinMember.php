<?php

namespace PHPCentroid\Query;

class ResolvingJoinMember {
    public function __construct(public mixed $target, public mixed $member, public string $fullyQualifiedMember)
    {
        //
    }
}