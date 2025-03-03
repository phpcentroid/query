<?php

namespace PHPCentroid\Tests\Query;

use PHPCentroid\Tests\Sqlite\SqliteAdapter;

class TestDatabase extends SqliteAdapter
{
    public function __construct()
    {
        $database = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'local.db');
        parent::__construct(['database' => $database]);
    }

}