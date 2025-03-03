<?php

namespace PHPCentroid\Tests\Query;

use Exception;
use PHPCentroid\Query\ClosureParser;
use PHPCentroid\Query\QueryExpression;
use PHPCentroid\Query\SqlFormatter;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class ClosureParserTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    public function testParseSelect()
    {
        $closure = function ($a) {
            return array($a->id, $a->name, $a->age);
        };
        $parser = new ClosureParser();
        $ast = $parser->parseSelect($closure);
        $this->assertIsArray($ast, 'ClosureParser::parse() should return an array');
        $this->assertCount(3, $ast, 'ClosureParser::parse() should return an array with 3 elements');
    }

    /**
     * @throws ReflectionException
     */
    public function testParseSelectWithAlias()
    {
        $closure = function ($a) {
            return array(
                'id' => $a->id,
                'name' => $a->name,
                'age' => $a->age
            );
        };
        $parser = new ClosureParser();
        $select = $parser->parseSelect($closure);
        $this->assertIsArray($select, 'ClosureParser::parse() should return an array');
        $this->assertCount(3, $select, 'ClosureParser::parse() should return an array with 3 elements');
        $key = key($select);
        $value = current($select);
        $this->assertIsString($key);
        $this->assertNotEmpty($value);
        $this->assertEquals('id', $value['$getField']);
    }

    /**
     * @throws ReflectionException
     */
    public function testParseSelectQualifiedMember()
    {
        $closure = function ($a) {
            return array(
                $a->actionStatus->alternateName,
            );
        };
        $parser = new ClosureParser();
        $select = $parser->parseSelect($closure);
        $this->assertIsArray($select, 'ClosureParser::parseSelect() should return an array');
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testParseFilterExpr()
    {
        $closure = function ($a) {
            return $a->name === 'admin';
        };
        $parser = new ClosureParser();
        $filter = $parser->parseFilter($closure);
        $this->assertIsArray($filter, 'ClosureParser::parseFilter() should return an array');
        $formatter = new SqlFormatter();
        $sql = $formatter->escape($filter);
        $this->assertIsString($sql, 'SqlFormatter::format() should return a string');
        $this->assertEquals("`name` = 'admin'", $sql);

    }

    /**
     * @throws Exception
     */
    public function testParseAndExpression()
    {
        $closure = function ($a) {
            return $a->category === 'Laptops' && $a->price < 1000;
        };
        $parser = new ClosureParser();
        $filter = $parser->parseFilter($closure);
        $this->assertIsArray($filter);
        $formatter = new SqlFormatter();
        $sql = $formatter->escape($filter);
        $this->assertIsString($sql, 'SqlFormatter::format() should return a string');
        $this->assertEquals("(`category` = 'Laptops' AND `price` < 1000)", $sql);
    }

    /**
     * @throws Exception
     */
    public function testParseOrExpression()
    {
        $closure = function ($a) {
            return $a->category === 'Laptops' || $a->category  == 'Desktops';
        };
        $parser = new ClosureParser();
        $filter = $parser->parseFilter($closure);
        $this->assertIsArray($filter);
        $formatter = new SqlFormatter();
        $sql = $formatter->escape($filter);
        $this->assertIsString($sql);
        $this->assertEquals("(`category` = 'Laptops' OR `category` = 'Desktops')", $sql);
    }

    /**
     * @throws Exception
     */
    public function testParseMethodCallExpression()
    {
        $closure = function ($a) {
            return round($a->price, 2) == 1000;
        };
        $parser = new ClosureParser();
        $filter = $parser->parseFilter($closure);
        $this->assertIsArray($filter);
        $formatter = new SqlFormatter();
        $sql = $formatter->escape($filter);
        $this->assertIsString($sql);
        $this->assertEquals("ROUND(`price`, 2) = 1000", $sql);
    }

    /**
     * @throws Exception
     */
    public function testParseMemberWithMethodCall()
    {
        $closure = function ($a) {
            return array(
                'id' => $a->id,
                'price' => round($a->price, 2),
            );
        };
        $parser = new ClosureParser();
        $select = $parser->parseSelect($closure);
        $this->assertIsArray($select);
    }

    /**
     * @throws \Exception
     */
    public function testExecuteSelectWithClosure()
    {
        $db = new TestDatabase();
        $db->open();
        $query = (new QueryExpression())->select(
            function($a) {
                return [
                    'id' => $a->id,
                    'name' => $a->name,
                    'description' => $a->description,
                    'dateCreated' => $a->dateCreated
                ];
            }
        )->from('UserData')->where('name')->equal('alexis.rees@example.com');
        $result = $db->execute($query);
        $this->assertNotNull($result);
        $user = (object)$result[0];
        $this->assertEquals('alexis.rees@example.com', $user->name);
        $db->close();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testExecuteSelectWithArrayClosure()
    {
        $db = new TestDatabase();
        $db->open();
        $query = (new QueryExpression())->select(
            function($a) {
                return array(
                    $a->id,
                    $a->name,
                    $a->price,
                    $a->dateCreated
                );
            }
        )->from('ProductData')->where('name')->equal('Lenovo Yoga 2 Pro');
        $result = $db->execute($query);
        $this->assertNotNull($result);
        $product = (object)$result[0];
        $this->assertEquals('Lenovo Yoga 2 Pro', $product->name);
        $db->close();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testExecuteFilterClosure()
    {
        $db = new TestDatabase();
        $db->open();
        $query = (new QueryExpression())->select(
            function($a) {
                return array(
                    $a->id,
                    $a->name,
                    $a->price,
                    $a->dateCreated
                );
            }
        )->from('ProductData')->where(function($a, $name) {
            return $a->name == $name;
        }, 'Lenovo Yoga 2 Pro');
        $result = $db->execute($query);
        $this->assertNotNull($result);
        $product = (object)$result[0];
        $this->assertEquals('Lenovo Yoga 2 Pro', $product->name);
        $db->close();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testExecuteSelectWithSingleField()
    {
        $db = new TestDatabase();
        $db->open();
        $query = (new QueryExpression())->select(
            function($a) {
                return $a->name;
            }
        )->from('ProductData')->where(function($a, $id) {
            return $a->id == $id;
        }, 19);
        $result = $db->execute($query);
        $this->assertNotNull($result);
        $product = (object)$result[0];
        $this->assertEquals('Lenovo Yoga 2 Pro', $product->name);
        $db->close();
    }

}