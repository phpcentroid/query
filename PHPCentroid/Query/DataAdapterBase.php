<?php

namespace PHPCentroid\Query;

use PHPCentroid\Query\iQueryable;
use PHPCentroid\Query\QueryExpression;
use Closure;

interface DataAdapterBase
{
    /**
     * Open the connection.
     * @return void
     */
    public function open(): void;
    /**
     * Close the connection.
     * @return void
     */
    public function close(): void;

    /**
     * Get the raw connection object.
     * @return mixed
     */
    public function getRawConnection(): mixed;
    /**
     * Execute the query and return the result.
     * @param QueryExpression $query
     * @return mixed
     */
    public function execute(iQueryable $query): mixed;
    /**
     * Execute the query and return the result as an array.
     * @param Closure $callable
     * @return void
     */
    public function executeInTransaction(Closure $callable): void;

    /**
     * Get the last inserted identity.
     * @return mixed
     */
    public function selectIdentity(): mixed;

}