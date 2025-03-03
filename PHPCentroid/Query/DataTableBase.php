<?php

namespace PHPCentroid\Query;

interface DataTableBase
{
    public function __construct(DataAdapterBase $adapter, string $name);
    public function exists(): bool;
    public function create(array $columns): void;
    public function update(array $columns): void;
    public function drop(): void;

}