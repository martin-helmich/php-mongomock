<?php

namespace Helmich\MongoMock\Tests;

use Helmich\MongoMock\TypeMapper;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use PHPUnit\Framework\TestCase;

class TypeMapperTest extends TestCase
{
    function testMapWithDefaultOptions()
    {
        $typeMapper = TypeMapper::createWithDefault();
        $mapped     = $typeMapper->map(new BSONDocument(['foo' => 'bar', 'list' => [1, 2, 3]]));

        $this->assertInstanceOf(BSONDocument::class, $mapped);
        $this->assertArrayHasKey('foo', $mapped);
        $this->assertSame('bar', $mapped['foo']);
        $this->assertInstanceOf(BSONArray::class, $mapped['list']);
        $this->assertCount(3, $mapped['list']);
    }

    function testMapWithDocumentAndArraysAsArrayOption()
    {
        $typeMapper = TypeMapper::createWithDefault(['document' => 'array', 'array' => 'array']);
        $mapped     = $typeMapper->map(new BSONDocument(['foo' => 'bar', 'list' => [1, 2, 3]]));

        $this->assertArrayHasKey('foo', $mapped);
        $this->assertSame('bar', $mapped['foo']);

        // workaround for missing assertIsArray() in phpunit6 and deprecated assertInternalType in phpunit8
        $this->assertTrue(is_array($mapped['list']));
        $this->assertCount(3, $mapped['list']);
    }

    function testMapWithDocumentAsCustomTypeOption()
    {
        $typeMapper = TypeMapper::createWithDefault(['document' => BSONArray::class]);
        $mapped     = $typeMapper->map(new BSONDocument(['foo' => 'bar', 'list' => [1, 2, 3]]));

        $this->assertInstanceOf(BSONArray::class, $mapped);
        $this->assertArrayHasKey('foo', $mapped);
        $this->assertSame('bar', $mapped['foo']);
        $this->assertInstanceOf(BSONArray::class, $mapped['list']);
        $this->assertCount(3, $mapped['list']);
    }

    function testMapWithSubObjectAsArray()
    {
        $typeMapper = TypeMapper::createWithDefault();
        $mapped     = $typeMapper->map(new BSONDocument(['foo' => ['bar' => 1, 'baz' => 2]]));

        $this->assertInstanceOf(BSONDocument::class, $mapped);
        $this->assertArrayHasKey('foo', $mapped);
        $this->assertInstanceOf(BSONDocument::class, $mapped['foo']);
        $this->assertSame(1, $mapped['foo']['bar']);
        $this->assertSame(2, $mapped['foo']['baz']);
    }

}