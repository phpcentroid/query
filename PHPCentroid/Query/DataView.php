<?php

namespace PHPCentroid\Query;

abstract class DataView
{
    public DataAdapterBase $db;
    public string $table;

    public function __construct(string $table, DataAdapterBase $db)
    {
        $this->table = $table;
        $this->db = $db;
    }

    public abstract function create(QueryExpression $query): void;
    public abstract function change(QueryExpression $query): void;
    public abstract function drop(): void;
    public abstract function exists(): bool;
}