<?php

namespace PHPCentroid\Query;

use PHPCentroid\Query\QueryExpression;

interface DataViewBase
{
    public function __construct(DataAdapterBase $adapter, string $name);
    public function exists(): bool;
    public function create(QueryExpression $query): void;
    public function update(QueryExpression $query): void;
    public function drop(): void;
}