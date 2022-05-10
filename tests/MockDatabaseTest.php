<?php

namespace Helmich\MongoMock\Tests;

use Helmich\MongoMock\MockDatabase;
use PHPUnit\Framework\TestCase;
use MongoDB\Model\CollectionInfoIterator;

class MockDatabaseTest extends TestCase
{
    public function testListCollections(){
        $db = new MockDatabase("FooBar");

        $db->createCollection("foo");
        $db->createCollection("bar");
        $db->createCollection("baz");

        $collectionNames = [
            'FooBar.foo',
            'FooBar.bar',
            'FooBar.baz'
        ];

        $result = $db->listCollections();
        self::assertInstanceOf(CollectionInfoIterator::class,$result);

        $names = [];
        foreach($result as $col){
            self::assertArrayHasKey("name",$col);
            $names []= $col['name'];
        }
        
        self::assertThat($names,self::equalTo($collectionNames));
    }
}