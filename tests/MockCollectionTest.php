<?php

namespace Helmich\MongoMock\Tests;

use Helmich\MongoMock\MockCollection;
use Helmich\MongoMock\MockCursor;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\Regex;
use MongoDB\Collection;
use MongoDB\InsertManyResult;
use MongoDB\InsertOneResult;
use MongoDB\UpdateResult;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use MongoDB\Driver\Exception\RuntimeException as DriverRuntimeException;
use PHPUnit\Framework\TestCase;

class MockCollectionTest extends TestCase
{
    /** @var Collection */
    private $col;

    public function setUp(): void
    {
        $this->col = new MockCollection();
    }

    public function testInsertOneInsertsDocument()
    {
        $result = $this->col->insertOne(['foo' => 'bar']);

        assertThat($result, isInstanceOf(InsertOneResult::class));
        assertThat($result->getInsertedCount(), equalTo(1));
        assertThat($result->getInsertedId(), isInstanceOf(ObjectID::class));
        assertThat($result->isAcknowledged(), isTrue());

        $find = $this->col->findOne(['_id' => $result->getInsertedId()]);

        assertThat($find, logicalNot(isNull()));
        assertThat($find['foo'], equalTo('bar'));
    }

    public function testInsertOneDocumentWithExistingId()
    {
        $result = $this->col->insertOne([
            '_id' => 'baz',
            'foo' => 'bar'
        ]);

        $this->expectException(DriverRuntimeException::class);
        
        // inserting a document with the same _id (baz)
        $result = $this->col->insertOne([
            '_id' => 'baz',
            'bat' => 'dog'
        ]);        
    }

    /**
     * @depends testInsertOneInsertsDocument
     */
    public function testInsertOneConvertsArraysToBSON()
    {
        $result = $this->col->insertOne(['foo' => 'bar']);
        $find = $this->col->findOne(['_id' => $result->getInsertedId()]);

        assertThat($find, isInstanceOf(BSONDocument::class));
    }

    /**
     * @depends testInsertOneInsertsDocument
     */
    public function testFinddOneDocumentArrayField()
    {
        $result = $this->col->insertOne(['foo' => [1, 2, 3]]);
        $find = $this->col->findOne(['_id' => $result->getInsertedId()]);

        assertThat($find['foo'], isInstanceOf(BSONArray::class));
    }

    /**
     * @depends testInsertOneInsertsDocument
     */
    public function testFindOneDocumentDeepArrayField()
    {
        $result = $this->col->insertOne(['foo' => [0 => [1, 2, 3], 2, 3]]);
        $find = $this->col->findOne(['_id' => $result->getInsertedId()]);

        assertThat($find['foo'][0], isInstanceOf(BSONArray::class));
    }

    /**
     * @depends testInsertOneInsertsDocument
     */
    public function testFindOneDocumentTypeMapArrayFromCollection()
    {
        $col = new MockCollection('test', null, [
            'typeMap' => [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array'
            ]
        ]);
        $result = $col->insertOne(['foo' => [0 => [1, 2, 3], 2, 3]]);
        $find = $col->findOne(['_id' => $result->getInsertedId()]);

        assertThat(is_array($find), isTrue());
        assertThat(is_array($find['foo']), isTrue());
        assertThat(is_array($find['foo'][0]), isTrue());
    }

    /**
     * @depends testInsertOneInsertsDocument
     */
    public function testFindOneDocumentTypeMapArray()
    {
        $result = $this->col->insertOne(['foo' => [0 => [1, 2, 3]]]);
        $find = $this->col->findOne(['_id' => $result->getInsertedId()], [
            'typeMap' => [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array'
            ]
        ]);

        assertThat(is_array($find), isTrue());
        assertThat(is_array($find['foo']), isTrue());
        assertThat(is_array($find['foo'][0]), isTrue());
    }

    /**
     * @depends testInsertOneInsertsDocument
     */
    public function testFindManyDocumentTypeMapArrayFromCollection()
    {
        $col = new MockCollection('test', null, [
            'typeMap' => [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array'
            ]
        ]);

        $col->insertMany([
            ['foo' => 'foo', 'bar' => 1],
            ['foo' => 'bar', 'bar' => 1],
        ]);

        $find = $col->find();
        $result = iterator_to_array($find);

        assertThat(is_array($result[0]), isTrue());
        assertThat(is_array($result[1]), isTrue());
    }

    /**
     * @depends testInsertOneInsertsDocument
     */
    public function testFindWithInvertedFilter()
    {
        $this->col->insertMany([
            ['foo' => 'bar'],
            ['foo' => 'baz']
        ]); 
        
        $result = $this->col->count(['foo' => ['$not' => ['$in' => ['bar', 'baz']]]]);
        assertThat($result, equalTo(0));

        $find = $this->col->find(['foo' => ['$not' => ['$eq' => 'baz']]]);
        $result = $find->toArray();
        assertThat(count($result), equalTo(1));
        assertThat($result[0]['foo'], equalTo('bar'));

        $find = $this->col->find(['foo' => ['$not' => 
            ['$not' => 
                ['$eq' => 'bar']
            ]
        ]]);
        $result = $find->toArray();
        assertThat(count($result), equalTo(1));
        assertThat($result[0]['foo'], equalTo('bar'));
    }

    /**
     * @depends testInsertOneInsertsDocument
     */
    public function testFindWithInFilter()
    {
        $this->col->insertMany([
            ['foo' => ['bar', 'baz', 'bad']],
            ['foo' => ['baz', 'bad']],
            ['foo' => ['foobar', 'baroof']]
        ]); 

        $result = $this->col->count(['foo' => ['$in' => ['barbar']]]);
        assertThat($result, equalTo(0));

        $result = $this->col->count(['foo' => ['$in' => ['bar']]]);
        assertThat($result, equalTo(1));

        $result = $this->col->count(['foo' => ['$in' => ['bar', 'baz']]]);
        assertThat($result, equalTo(2));

        $result = $this->col->count(['foo' => ['$in' => ['foobar', 'bad']]]);
        assertThat($result, equalTo(3));
    }

    /**
     * @depends testInsertOneInsertsDocument
     */
    public function testInsertOneKeepsBSONObjects()
    {
        $result = $this->col->insertOne(new BSONDocument(['foo' => 'bar']));
        $find = $this->col->findOne(['_id' => $result->getInsertedId()]);

        assertThat($find, isInstanceOf(BSONDocument::class));
        assertThat($find['foo'], equalTo('bar'));
    }

    /**
     * @depends testInsertOneInsertsDocument
     */
    public function testInsertOneKeepsIdIfSet()
    {
        $id = new ObjectID();
        $result = $this->col->insertOne(['_id' => $id, 'foo' => 'bar']);

        assertThat($result->getInsertedId(), equalTo($id));

        $find = $this->col->findOne(['_id' => $id]);
        assertThat($find, isInstanceOf(BSONDocument::class));
        assertThat($find['foo'], equalTo('bar'));
    }

    public function testInsertManyInsertsDocuments()
    {
        $result = $this->col->insertMany([
            ['foo' => 'foo'],
            ['foo' => 'bar'],
            ['foo' => 'baz'],
        ]);

        assertThat($result, isInstanceOf(InsertManyResult::class));
        assertThat($result->getInsertedCount(), equalTo(3));
        assertThat(count($result->getInsertedIds()), equalTo(3));
        assertThat($result->isAcknowledged(), isTrue());

        assertThat($this->col->count(['foo' => 'foo']), equalTo(1));
        assertThat($this->col->count(['foo' => 'bar']), equalTo(1));
        assertThat($this->col->count(['foo' => 'baz']), equalTo(1));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testDeleteManyDeletesObjects()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 1],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);
        $this->col->deleteMany(['bar' => 1]);

        assertThat($this->col->count(['bar' => 1]), equalTo(0));
        assertThat($this->col->count(['bar' => 2]), equalTo(1));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testDeleteOneDeletesJustOneObject()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 1],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);
        $this->col->deleteOne(['bar' => 1]);

        assertThat($this->col->count(['bar' => 1]), equalTo(1));
        assertThat($this->col->count(['bar' => 2]), equalTo(1));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testUpdateOneUpdatesOneObject()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 1],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);
        $result = $this->col->updateOne(['bar' => 1], ['$set' => ['foo' => 'Kekse']]);
        assertThat($result, isInstanceOf(UpdateResult::class));

        assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), equalTo(1));
        assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), equalTo(1));
        assertThat($this->col->count(['bar' => 2]), equalTo(1));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testUpdateOneUpdatesNothingWhenNothingMatches()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 1],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);
        $this->col->updateOne(['bar' => 9], ['$set' => ['foo' => 'Kekse']]);

        assertThat($this->col->count(['foo' => 'Kekse']), equalTo(0));
        assertThat($this->col->count(['bar' => 1, 'foo' => 'foo']), equalTo(1));
        assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), equalTo(1));
        assertThat($this->col->count(['bar' => 2]), equalTo(1));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testUpdateOneSupportsUnset()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 1],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);
        $this->col->updateOne(['bar' => 2], ['$unset' => ['foo' => '']]);

        assertThat($this->col->count(['foo' => 'baz']), equalTo(0));
        assertThat($this->col->count(['bar' => 1, 'foo' => 'foo']), equalTo(1));
        assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), equalTo(1));
        assertThat($this->col->count(['bar' => 2]), equalTo(1));
        assertThat($this->col->count(['bar' => 2, 'foo' => 'baz']), equalTo(0));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testUpdateManyUpdatesManyObjects()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 1],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);
        $result = $this->col->updateMany(['bar' => 1], ['$set' => ['foo' => 'Kekse']]);
        assertThat($result, isInstanceOf(UpdateResult::class));

        assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), equalTo(2));
        assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), equalTo(0));
        assertThat($this->col->count(['bar' => 2]), equalTo(1));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testUpdateManySupportsUnset()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 1],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);
        $this->col->updateMany(['bar' => 1], ['$unset' => ['foo' => '']]);
        assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), equalTo(0));
        assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), equalTo(0));
        assertThat($this->col->count(['bar' => 2]), equalTo(1));
        assertThat($this->col->count(['bar' => 1]), equalTo(2));

        // test that inexistant fields do not affect result
        $this->col->updateMany(['bar' => 1], ['$unset' => ['inexistant' => '']]);
        assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), equalTo(0));
        assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), equalTo(0));
        assertThat($this->col->count(['bar' => 2]), equalTo(1));
        assertThat($this->col->count(['bar' => 1]), equalTo(2));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testUpdateUpdatesManyObjects()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 1],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);
        $this->col->updateMany(['bar' => 1], ['$set' => ['foo' => 'Kekse']]);

        assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), equalTo(2));
        assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), equalTo(0));
        assertThat($this->col->count(['bar' => 2]), equalTo(1));
    }

    public function updateUpsertCore($x1, $x2, $x3)
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 1],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);

        $this->col->updateMany(['bar' => 1], $x1, ['upsert' => true]);
        assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), equalTo(2));
        assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), equalTo(0));
        assertThat($this->col->count(['bar' => 2]), equalTo(1));

        $this->col->updateMany(['bar' => 3], $x2, ['upsert' => true]);
        assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), equalTo(2));
        assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), equalTo(0));
        assertThat($this->col->count(['bar' => 2]), equalTo(1));
        assertThat($this->col->count(['bar' => 3]), equalTo(1));

        $this->col->updateOne(['bar' => 1], $x3, ['upsert' => true]);
        assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), equalTo(1));
        assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), equalTo(1));
        assertThat($this->col->count(['bar' => 2]), equalTo(1));

        $this->col->updateOne(['bar' => 4], $x3, ['upsert' => true]);
        assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), equalTo(1));
        if (array_key_exists('$set', $x3)) {
            assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), equalTo(1));
        } else {
            assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), equalTo(2));
        }
        assertThat($this->col->count(['bar' => 2]), equalTo(1));
        if (array_key_exists('$set', $x3)) {
            assertThat($this->col->count(['bar' => 4]), equalTo(1));
        } else {
            assertThat($this->col->count(['bar' => 4]), equalTo(0));
        }
    }


    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testUpdateUpsertCanUpdateAndInsert()
    {
        $this->updateUpsertCore(
            ['$set' => ['foo' => 'Kekse']],
            ['$set' => ['foo' => 'Kekse']],
            ['$set' => ['foo' => 'bar']]
        );
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testUpdateIncrement()
    {
        $this->col->insertOne(
            ['foo' => 'foo', 'bar' => 0]
        );

        $this->col->updateMany([], ['$inc' => ['bar' => 1]], ['upsert' => true]);
        assertThat($this->col->count(['bar' => 1]), equalTo(1));

        $this->col->insertOne(
            ['foo' => 'foo', 'bar' => 1]
        );

        $this->col->updateMany([], ['$inc' => ['bar' => -1]], ['upsert' => true]);
        assertThat($this->col->count(['bar' => 0]), equalTo(2));

    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testUpdatePush()
    {
         $this->col->insertOne(
            ['foo' => 'foo', 'bar' => []]
        );

        $this->col->updateMany([], ['$push' => ['bar' => 'bar']]);
        assertEquals($this->col->count(['bar' => ['$size' => 1]]), 1);
    }


    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testUpdateUpsertWithoutAtomicModifier()
    {
        try {
            $this->updateUpsertCore(
                ['bar' => 1, 'foo' => 'Kekse'],
                ['bar' => 3, 'foo' => 'Kekse'],
                ['bar' => 1, 'foo' => 'bar']
            );
            $this->assertTrue(false); // shouldnt get here
        } catch (\Exception $e) {
            $this->assertTrue(true); // should get here
        }
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testUpdateConstraintIn()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 1],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
            ['foo' => 'yoo', 'bar' => 3],
        ]);
        $this->col->updateMany(
            ['bar' => ['$in' => [1, 3]]],
            ['$set' => ['foo' => 'Kekse']],
            ['upsert' => true]);

        assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), equalTo(2));
        assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), equalTo(0));
        assertThat($this->col->count(['bar' => 2]), equalTo(1));
        assertThat($this->col->count(['bar' => 3, 'foo' => 'Kekse']), equalTo(1));
        assertThat($this->col->count(['bar' => 3, 'foo' => 'yoo']), equalTo(0));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testFindCanSortResults()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);
        $result = $this->col->find([], ['sort' => ['bar' => 1]]);
        $result = iterator_to_array($result);

        assertThat(count($result), equalTo(3));
        assertThat($result[0]['bar'], equalTo(1));
        assertThat($result[1]['bar'], equalTo(2));
        assertThat($result[2]['bar'], equalTo(3));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testFindCanSortResultsDescending()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);
        $result = $this->col->find([], ['sort' => ['bar' => -1]]);
        $result = iterator_to_array($result);

        assertThat(count($result), equalTo(3));
        assertThat($result[0]['bar'], equalTo(3));
        assertThat($result[1]['bar'], equalTo(2));
        assertThat($result[2]['bar'], equalTo(1));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testFindCanSortResultsWithMultipleProperties()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
            ['foo' => 'zab', 'bar' => 2],
        ]);
        $result = $this->col->find([], ['sort' => ['bar' => 1, 'foo' => -1]]);
        $result = iterator_to_array($result);

        assertThat(count($result), equalTo(4));
        assertThat($result[0]['bar'], equalTo(1));
        assertThat($result[1]['bar'], equalTo(2));
        assertThat($result[1]['foo'], equalTo('zab'));
        assertThat($result[2]['bar'], equalTo(2));
        assertThat($result[2]['foo'], equalTo('baz'));
        assertThat($result[3]['bar'], equalTo(3));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testFindCanSkipResults()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);
        $result = $this->col->find([], ['sort' => ['bar' => 1], 'skip' => 1]);
        $result = iterator_to_array($result);

        assertThat(count($result), equalTo(2));
        assertThat($result[0]['bar'], equalTo(2));
        assertThat($result[1]['bar'], equalTo(3));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testFindCanLimitResults()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);
        $result = $this->col->find([], ['sort' => ['bar' => 1], 'limit' => 2]);
        $result = iterator_to_array($result);

        assertThat(count($result), equalTo(2));
        assertThat($result[0]['bar'], equalTo(1));
        assertThat($result[1]['bar'], equalTo(2));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testFindWorksWithCallableOperators()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);
        $result = $this->col->find([
            'foo' => function ($var): bool {
                return $var == 'bar';
            }
        ]);
        $result = iterator_to_array($result);
        
        assertThat(count($result), equalTo(1));
        assertThat($result[0]['foo'], equalTo('bar'));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testFindWorksWithPhpUnitConstraints()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);
        $result = $this->col->find([
            'foo' => equalTo('bar')
        ]);
        $result = iterator_to_array($result);
        
        assertThat(count($result), equalTo(1));
        assertThat($result[0]['foo'], equalTo('bar'));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testFindWorksWithExists()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3, 'krypton' => true],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);
        $result = $this->col->find([
            'foo' => '$exists'
        ]);
        $result = iterator_to_array($result);
        assertThat(count($result), equalTo(3));

        $result = $this->col->find([
            'krypton' => '$exists'
        ]);
        $result = iterator_to_array($result);
        assertThat(count($result), equalTo(1));
        assertThat($result[0]['foo'], equalTo('foo'));

        $result = $this->col->find([
            'inexistant' => '$exists'
        ]);
        $result = iterator_to_array($result);
        assertThat(count($result), equalTo(0));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testFindOneFindsOne()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);
        $result = $this->col->findOne(['bar' => ['$lt' => 3]], ['sort' => ['bar' => -1]]);

        assertThat($result['foo'], equalTo('baz'));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testFindOneByRegex()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);

        $regex = new Regex('^Foo', 'i');
        $result = $this->col->findOne(['foo' => $regex]);

        assertThat($result['foo'], equalTo('foo'));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testFindOneByRegexWithDelimiterInRegex()
    {
        $this->col->insertMany([
            ['foo' => '#', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);

        $regex = new Regex('#', 'i');
        $result = $this->col->findOne(['foo' => $regex]);

        assertThat($result['foo'], equalTo('#'));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testFindNoneByRegex()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);

        $regex = new Regex('Foo');
        $result = $this->col->findOne(['foo' => $regex]);

        assertThat($result, equalTo(null));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testFindOneByAndQuery()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);

        $result = $this->col->findOne(['$and' => [['foo' => 'foo'], ['bar' => 3]]]);
        assertThat($result['foo'], equalTo('foo'));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testFindNoneByAndQuery()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);

        $result = $this->col->findOne(['$and' => [['foo' => 'foo'], ['bar' => 1]]]);
        assertThat($result, equalTo(null));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testManyByOrQuery()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);

        $result = $this->col->find(['$or' => [['foo' => 'foo'], ['foo' => 'baz']]]);
        assertThat($result, isInstanceOf(MockCursor::class));
        $result = $result->toArray();
        assertThat(count($result), equalTo(2));
        assertThat($result[0]['foo'], equalTo('foo'));
        assertThat($result[1]['foo'], equalTo('baz'));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testManyByOrAndQuery()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);

        $result = $this->col->find(['$or' => [
            ['$and' => [['foo' => 'foo'], ['bar' => '3']]],
            ['$and' => [['foo' => 'baz'], ['bar' => '2']]],
        ]]);

        assertThat($result, isInstanceOf(MockCursor::class));
        $result = $result->toArray();
        assertThat(count($result), equalTo(2));
        assertThat($result[0]['foo'], equalTo('foo'));
        assertThat($result[1]['foo'], equalTo('baz'));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testManyByAndOrQuery()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);

        $result = $this->col->find(['$and' => [
            ['$or' => [['foo' => 1], ['foo' => 'foo']]],
            ['$or' => [['bar' => 'foo'], ['bar' => 3]]],
        ]]);

        assertThat($result, isInstanceOf(MockCursor::class));
        $result = $result->toArray();
        assertThat(count($result), equalTo(1));
        assertThat($result[0]['foo'], equalTo('foo'));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testManyByNorQuery()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);

        $result = $this->col->count(['$nor' => [
            'foo' => ['$eq' => 'foo'],
            'foo' => ['$eq' => 'bar'],
            'foo' => ['$eq' => 'baz']
        ]]);
        assertThat($result, equalTo(0));

        /* Finding ['foo' => 'foo', 'bar' => 3] */
        $result = $this->col->count(['$nor' => [
            ['foo' => ['$eq' => 'bar']],
            ['foo' => ['$eq' => 'baz']],
            ['bar' => ['$lt' => 3]]
        ]]);
        assertThat($result, equalTo(1));

        /* Finding ['foo' => 'bar', 'bar' => 1] */
        $result = $this->col->count(['$nor' => [
            ['foo' =>
                ['$not' => ['$eq' => 'bar']]
            ],
            ['bar' =>
                ['$not' => ['$eq' => 1]]
            ]
        ]]);
        assertThat($result, equalTo(1));
    }


    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testOneSubFieldQuery()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => ['foo' => 1]],
            ['foo' => 'bar', 'bar' => ['foo' => 2]],
            ['foo' => 'baz', 'bar' => ['foo' => 3]],
        ]);

        $result = $this->col->findOne(
            ['bar.foo' => 1]
        );
        assertThat($result['foo'], equalTo('foo'));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testManyExistsQuery()
    {
        $this->col->insertMany([
            ['foo' => 'for', 'bar' => 4],
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'ba' => 2],
        ]);

        $result = $this->col->find(
            ['bar' => ['$exists' => 1]]
        );

        assertThat($result, isInstanceOf(MockCursor::class));
        $result = $result->toArray();
        assertThat(count($result), equalTo(3));
        assertThat($result[0]['foo'], equalTo('for'));
        assertThat($result[1]['foo'], equalTo('foo'));
        assertThat($result[2]['foo'], equalTo('bar'));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testManyOrGteAndLteQuery()
    {
        $this->col->insertMany([
            ['foo' => 'for', 'bar' => 4],
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);

        $result = $this->col->find(['$or' => [
            ['bar' => ['$lte' => 1]],
            ['bar' => ['$gte' => 3]]
        ]]);

        assertThat($result, isInstanceOf(MockCursor::class));
        $result = $result->toArray();
        assertThat(count($result), equalTo(3));
        assertThat($result[0]['foo'], equalTo('for'));
        assertThat($result[1]['foo'], equalTo('foo'));
        assertThat($result[2]['foo'], equalTo('bar'));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testManyElementMatchEqQuery()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);

        $result = $this->col->find(
            ['bar' => ['$elemMatch' => ['$eq' => 3]]]
        );

        assertThat($result, isInstanceOf(MockCursor::class));
        $result = $result->toArray();
        assertThat(count($result), equalTo(1));
        assertThat($result[0]['foo'], equalTo('foo'));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testManyElementMatchInQuery()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => [['foobar' => 1], ['foobar' => 2]]],
            ['foo' => 'foo', 'bar' => [['foobar' => 4], ['foobar' => 5]]],
            ['foo' => 'bar', 'bar' => [['foobar' => 3], ['foobar' => 4]]],
            ['foo' => 'baz', 'bar' => [['foobar' => 1]]],
        ]);

        $result = $this->col->find(
            ['bar' => ['$elemMatch' => ['foobar' => ['$in' => [1, 3]]]]]
        );

        assertThat($result, isInstanceOf(MockCursor::class));
        $result = $result->toArray();
        assertThat(count($result), equalTo(3));
        assertThat($result[0]['foo'], equalTo('foo'));
        assertThat($result[1]['foo'], equalTo('bar'));
        assertThat($result[2]['foo'], equalTo('baz'));
    }

    /**
     * @depends testInsertManyInsertsDocuments
     */
    public function testFindOneReturnsNullWhenNotFound()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);
        $result = $this->col->findOne(['bar' => ['$lt' => 1]], ['sort' => ['bar' => -1]]);

        assertThat($result, isNull());
    }

    public function testCollectionGetName()
    {
        $col = new MockCollection('foo');
        assertThat($col->getCollectionName(), equalTo('foo'));
    }

    public function testCreateIndexRegistersIndex()
    {
        $col = new MockCollection('foo');
        $col->createIndex('foo', ['unique' => true]);
        $col->createIndex('bar');

        $indices = iterator_to_array($col->listIndexes());

        assertThat(count($indices), equalTo(2));

        $first = $indices[0];
        $second = $indices[1];

        assertThat($first['unique'], isTrue());
        assertThat($second['unique'], isFalse());
        assertThat($first['key'], equalTo('foo'));
    }

    public function testCreateIndexRegistersMultifieldIndex()
    {
        $col = new MockCollection('foo');
        $col->createIndex(['foo' => 1, 'bar' => -1], ['unique' => true]);

        $indices = iterator_to_array($col->listIndexes());

        assertThat(count($indices), equalTo(1));

        $first = $indices[0];

        assertThat($first['unique'], isTrue());
        assertThat($first['key'], equalTo(['foo' => 1, 'bar' => -1]));
        assertThat($first['name'], equalTo('foo_1_bar_1'));
    }

    public function testFindReturnsClonesNotReferences ()
    {
        $collection = new MockCollection('anyCollection');
        $documentId = $collection->insertOne(['foo' => 'bar', 'bax' => ['hello' => 'world']])->getInsertedId();

        $documentBeforeUpdate = $collection->findOne(['_id' => $documentId]);
        $collection->updateOne(['_id' => $documentId], ['$set' => ['foo' => 'baz', 'bax' => ['hello' => 'planet']]]);
        $documentAfterUpdate = $collection->findOne(['_id' => $documentId]);

        // Test shallow object
        assertNotSame($documentBeforeUpdate, $documentAfterUpdate);
        assertThat($documentBeforeUpdate['foo'], equalTo('bar'));
        assertThat($documentAfterUpdate['foo'], equalTo('baz'));

        // Test sub-documents
        $subDocumentBeforeUpdate = $documentBeforeUpdate['bax'];
        $subDocumentAfterUpdate = $documentAfterUpdate['bax'];
        assertNotSame($subDocumentBeforeUpdate, $subDocumentAfterUpdate);
        assertThat($subDocumentBeforeUpdate['hello'], equalTo('world'));
        assertThat($subDocumentAfterUpdate['hello'], equalTo('planet'));
    }

}
