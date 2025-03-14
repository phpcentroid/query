<?php

namespace PHPCentroid\Tests\Query;

use Exception;
use PHPCentroid\Query\ClosureParser;
use PHPCentroid\Query\QueryExpression;
use PHPCentroid\Query\SqlFormatter;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class SelectQueryTest extends TestCase
{
    public function testSelectUsingStringExpression()
    {
        $db = new TestDatabase();
        $db->open();
        $query = (new QueryExpression())->select(
            'id', 'name', 'price', 'category'
        )->from('ProductData')->where('category')->equal('Laptops');
        $items = $db->execute($query);
        $this->assertGreaterThan(0, count($items));
        foreach ($items as $item) {
            $product = (object)$item;
            $this->assertEquals('Laptops', $product->category);
        }
        $db->close();
    }

    public function testSelectUsingClosure()
    {
        $db = new TestDatabase();
        $db->open();
        $query = (new QueryExpression())->select(
            function($a) {
                return [
                    $a->id,
                    $a->name,
                    $a->price,
                    $a->category
                ];
            }
        )->from('ProductData')->where(function($a) {
            return $a->category == 'Laptops';
        });
        $items = $db->execute($query);
        $this->assertGreaterThan(0, count($items));
        foreach ($items as $item) {
            $product = (object)$item;
            $this->assertEquals('Laptops', $product->category);
        }
        $db->close();
    }

    
}