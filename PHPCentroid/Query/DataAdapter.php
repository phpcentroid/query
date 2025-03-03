<?php

namespace PHPCentroid\Query;

use Closure;
use PHPCentroid\Query\iQueryable;
use PHPCentroid\Query\SqlFormatter;

abstract class DataAdapter implements DataAdapterBase
{
    protected mixed $rawConnection = NULL;

    public function __construct()
    {
        //
    }

    abstract public function open(): void;
    abstract public function close(): void;
    abstract public function execute(iQueryable $query): mixed;
    abstract public function executeInTransaction(Closure $callable): void;
    abstract function getTable(string $table): DataTableBase;
    abstract function getView(string $view): DataViewBase;
    abstract function getFormatter(): SqlFormatter;

    public function getRawConnection(): mixed
    {
        return $this->rawConnection;
    }

    public function setRawConnection(mixed $rawConnection): void
    {
        $this->rawConnection = $rawConnection;
    }

}