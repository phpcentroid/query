<?php

namespace PHPCentroid\Tests\Sqlite;

use Closure;
use Exception;
use PHPCentroid\Query\DataAdapter;
use PHPCentroid\Query\DataTableBase;
use PHPCentroid\Query\DataViewBase;
use PHPCentroid\Query\iQueryable;
use PHPCentroid\Query\SqlFormatter;
use SQLite3;

class SqliteAdapter extends DataAdapter
{
    /**
     * @var object
     */
    private object $connectOptions;
    private bool $transaction = false;

    public function __construct(array $connectOptions)
    {
        parent::__construct();
        $this->connectOptions = (object)$connectOptions;
    }

    public function open(): void
    {
        if ($this->rawConnection == NULL) {
            $connection = new SQLite3($this->connectOptions->database);
            $this->setRawConnection($connection);
        }
    }

    public function close(): void
    {
        if ($this->rawConnection != NULL) {
            $this->rawConnection->close();
            $this->setRawConnection(NULL);
        }
    }

    /**
     * @param iQueryable $query
     * @return mixed
     * @throws Exception
     */
    public function execute(iQueryable $query): mixed
    {
        // format the query
        $formatter = $this->getFormatter();
        $sql = $formatter->format($query);
        // execute the query
        return $this->executeQuery($sql);
    }

    private function executeQuery(string $sql): array
    {
        // open the connection
        $this->open();
        // execute the query
        $stmt = $this->rawConnection->prepare($sql);
        $result = $stmt->execute();
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        $result->finalize();
        return $rows;
    }

    /**
     * @throws Exception
     */
    public function executeInTransaction(Closure $callable): void
    {
        if ($this->transaction) {
            // execute the callable
            $callable();
            return;
        }
        try {
            // begin the transaction
            $this->executeQuery('BEGIN');
            // set the transaction flag
            $this->transaction = true;
            // execute the callable
            $callable();
            if ($this->transaction) {
                // commit the transaction
                $this->executeQuery('COMMIT');
                // clear the transaction flag
                $this->transaction = false;
            }
        } catch (Exception $e) {
            if ($this->transaction) {
                // rollback the transaction
                $this->executeQuery('ROLLBACK');
                // clear the transaction flag
                $this->transaction = false;
            }
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function getTable(string $table): DataTableBase
    {
        throw new Exception('Not implemented');
    }

    /**
     * @throws Exception
     */
    public function getView(string $view): DataViewBase
    {
        throw new Exception('Not implemented');
    }

    /**
     * @throws Exception
     */
    public function selectIdentity(): mixed
    {
        throw new Exception('Not implemented');
    }

    public function getFormatter(): SqlFormatter
    {
        return new SqliteFormatter();
    }
}