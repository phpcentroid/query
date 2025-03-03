<?php

namespace PHPCentroid\Query;

use Attribute;

#[Attribute] class SqlFormatterMethod
{
    public function __construct(private readonly ?string $name = NULL)
    {
        //
    }

    public function getName(): ?string {
        return $this->name;
    }

}