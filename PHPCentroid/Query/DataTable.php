<?php

namespace PHPCentroid\Query;

abstract class DataTable
{
    public DataAdapterBase $db;
    public string $table;

    public function __construct(string $table, DataAdapterBase $db)
    {
        $this->table = $table;
        $this->db = $db;
    }

    public abstract function create(array $fields): void;
    public abstract function change(array $fields): void;
    public abstract function drop(): void;
    public abstract function get_columns(): array;
    public abstract function get_indexes(): array;
    public abstract function exists(): bool;
    public abstract function get_version(): bool;


}