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
use PHPUnit\Framework\Assert;

class MockCollectionTest extends TestCase
{
    /** @var Collection */
    private $col;

    public function setUp()
    {
        $this->col = new MockCollection();
    }

    public function testInsertOneInsertsDocument()
    {
        $result = $this->col->insertOne(['foo' => 'bar']);

        TestCase::assertThat($result, Assert::isInstanceOf(InsertOneResult::class));
        TestCase::assertThat($result->getInsertedCount(), Assert::equalTo(1));
        TestCase::assertThat($result->getInsertedId(), Assert::isInstanceOf(ObjectID::class));
        TestCase::assertThat($result->isAcknowledged(), Assert::isTrue());

        $find = $this->col->findOne(['_id' => $result->getInsertedId()]);

        TestCase::assertThat($find, Assert::logicalNot(Assert::isNull()));
        TestCase::assertThat($find['foo'], Assert::equalTo('bar'));
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

        TestCase::assertThat($find, Assert::isInstanceOf(BSONDocument::class));
    }

    /**
     * @depends testInsertOneInsertsDocument
     */
    public function testFinddOneDocumentArrayField()
    {
        $result = $this->col->insertOne(['foo' => [1, 2, 3]]);
        $find = $this->col->findOne(['_id' => $result->getInsertedId()]);

        TestCase::assertThat($find['foo'], Assert::isInstanceOf(BSONArray::class));
    }

    /**
     * @depends testInsertOneInsertsDocument
     */
    public function testFindOneDocumentDeepArrayField()
    {
        $result = $this->col->insertOne(['foo' => [0 => [1, 2, 3], 2, 3]]);
        $find = $this->col->findOne(['_id' => $result->getInsertedId()]);

        TestCase::assertThat($find['foo'][0], Assert::isInstanceOf(BSONArray::class));
    }

    /**
     * @depends testInsertOneInsertsDocument
     */
    public function testFindOneDocumentTypeMapArrayFromCollection()
    {
        $col = new MockCollection('test', null, [
            'typeMap' => [
                'root'     => 'array',
                'document' => 'array',
                'array'    => 'array'
            ]
        ]);
        $result = $col->insertOne(['foo' => [0 => [1, 2, 3], 2, 3]]);
        $find = $col->findOne(['_id' => $result->getInsertedId()]);

        TestCase::assertThat(is_array($find), Assert::isTrue());
        TestCase::assertThat(is_array($find['foo']), Assert::isTrue());
        TestCase::assertThat(is_array($find['foo'][0]), Assert::isTrue());
    }

    /**
     * @depends testInsertOneInsertsDocument
     */
    public function testFindOneDocumentTypeMapArray()
    {
        $result = $this->col->insertOne(['foo' => [0 => [1, 2, 3]]]);
        $find = $this->col->findOne(['_id' => $result->getInsertedId()], [
            'typeMap' => [
                'root'     => 'array',
                'document' => 'array',
                'array'    => 'array'
            ]
        ]);

        TestCase::assertThat(is_array($find), Assert::isTrue());
        TestCase::assertThat(is_array($find['foo']), Assert::isTrue());
        TestCase::assertThat(is_array($find['foo'][0]), Assert::isTrue());
    }

    /**
     * @depends testInsertOneInsertsDocument
     */
    public function testFindManyDocumentTypeMapArrayFromCollection()
    {
        $col = new MockCollection('test', null, [
            'typeMap' => [
                'root'     => 'array',
                'document' => 'array',
                'array'    => 'array'
            ]
        ]);

        $col->insertMany([
            ['foo' => 'foo', 'bar' => 1],
            ['foo' => 'bar', 'bar' => 1],
        ]);

        $find = $col->find();
        $result = iterator_to_array($find);

        TestCase::assertThat(is_array($result[0]), Assert::isTrue());
        TestCase::assertThat(is_array($result[1]), Assert::isTrue());
    }

    /**
     * @depends testInsertOneInsertsDocument
     */
    public function testInsertOneKeepsBSONObjects()
    {
        $result = $this->col->insertOne(new BSONDocument(['foo' => 'bar']));
        $find = $this->col->findOne(['_id' => $result->getInsertedId()]);

        TestCase::assertThat($find, Assert::isInstanceOf(BSONDocument::class));
        TestCase::assertThat($find['foo'], Assert::equalTo('bar'));
    }

    /**
     * @depends testInsertOneInsertsDocument
     */
    public function testInsertOneKeepsIdIfSet()
    {
        $id = new ObjectID();
        $result = $this->col->insertOne(['_id' => $id, 'foo' => 'bar']);

        TestCase::assertThat($result->getInsertedId(), Assert::equalTo($id));

        $find = $this->col->findOne(['_id' => $id]);
        TestCase::assertThat($find, Assert::isInstanceOf(BSONDocument::class));
        TestCase::assertThat($find['foo'], Assert::equalTo('bar'));
    }

    public function testInsertManyInsertsDocuments()
    {
        $result = $this->col->insertMany([
            ['foo' => 'foo'],
            ['foo' => 'bar'],
            ['foo' => 'baz'],
        ]);

        TestCase::assertThat($result, Assert::isInstanceOf(InsertManyResult::class));
        TestCase::assertThat($result->getInsertedCount(), Assert::equalTo(3));
        TestCase::assertThat(count($result->getInsertedIds()), Assert::equalTo(3));
        TestCase::assertThat($result->isAcknowledged(), Assert::isTrue());

        TestCase::assertThat($this->col->count(['foo' => 'foo']), Assert::equalTo(1));
        TestCase::assertThat($this->col->count(['foo' => 'bar']), Assert::equalTo(1));
        TestCase::assertThat($this->col->count(['foo' => 'baz']), Assert::equalTo(1));
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

        TestCase::assertThat($this->col->count(['bar' => 1]), Assert::equalTo(0));
        TestCase::assertThat($this->col->count(['bar' => 2]), Assert::equalTo(1));
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

        TestCase::assertThat($this->col->count(['bar' => 1]), Assert::equalTo(1));
        TestCase::assertThat($this->col->count(['bar' => 2]), Assert::equalTo(1));
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
        TestCase::assertThat($result, Assert::isInstanceOf(UpdateResult::class));

        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), Assert::equalTo(1));
        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), Assert::equalTo(1));
        TestCase::assertThat($this->col->count(['bar' => 2]), Assert::equalTo(1));
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

        TestCase::assertThat($this->col->count(['foo' => 'Kekse']), Assert::equalTo(0));
        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'foo']), Assert::equalTo(1));
        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), Assert::equalTo(1));
        TestCase::assertThat($this->col->count(['bar' => 2]), Assert::equalTo(1));
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

        TestCase::assertThat($this->col->count(['foo' => 'baz']), Assert::equalTo(0));
        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'foo']), Assert::equalTo(1));
        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), Assert::equalTo(1));
        TestCase::assertThat($this->col->count(['bar' => 2]), Assert::equalTo(1));
        TestCase::assertThat($this->col->count(['bar' => 2, 'foo' => 'baz']), Assert::equalTo(0));
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
        TestCase::assertThat($result, Assert::isInstanceOf(UpdateResult::class));

        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), Assert::equalTo(2));
        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), Assert::equalTo(0));
        TestCase::assertThat($this->col->count(['bar' => 2]), Assert::equalTo(1));
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
        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), Assert::equalTo(0));
        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), Assert::equalTo(0));
        TestCase::assertThat($this->col->count(['bar' => 2]), Assert::equalTo(1));
        TestCase::assertThat($this->col->count(['bar' => 1]), Assert::equalTo(2));

        // test that inexistant fields do not affect result
        $this->col->updateMany(['bar' => 1], ['$unset' => ['inexistant' => '']]);
        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), Assert::equalTo(0));
        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), Assert::equalTo(0));
        TestCase::assertThat($this->col->count(['bar' => 2]), Assert::equalTo(1));
        TestCase::assertThat($this->col->count(['bar' => 1]), Assert::equalTo(2));
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

        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), Assert::equalTo(2));
        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), Assert::equalTo(0));
        TestCase::assertThat($this->col->count(['bar' => 2]), Assert::equalTo(1));
    }

    public function updateUpsertCore($x1, $x2, $x3)
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 1],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);

        $this->col->updateMany(['bar' => 1], $x1, ['upsert' => true]);
        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), Assert::equalTo(2));
        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), Assert::equalTo(0));
        TestCase::assertThat($this->col->count(['bar' => 2]), Assert::equalTo(1));

        $this->col->updateMany(['bar' => 3], $x2, ['upsert' => true]);
        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), Assert::equalTo(2));
        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), Assert::equalTo(0));
        TestCase::assertThat($this->col->count(['bar' => 2]), Assert::equalTo(1));
        TestCase::assertThat($this->col->count(['bar' => 3]), Assert::equalTo(1));

        $this->col->updateOne(['bar' => 1], $x3, ['upsert' => true]);
        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), Assert::equalTo(1));
        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), Assert::equalTo(1));
        TestCase::assertThat($this->col->count(['bar' => 2]), Assert::equalTo(1));

        $this->col->updateOne(['bar' => 4], $x3, ['upsert' => true]);
        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), Assert::equalTo(1));
        if (array_key_exists('$set', $x3)) {
            TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), Assert::equalTo(1));
        } else {
            TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), Assert::equalTo(2));
        }
        TestCase::assertThat($this->col->count(['bar' => 2]), Assert::equalTo(1));
        if (array_key_exists('$set', $x3)) {
            TestCase::assertThat($this->col->count(['bar' => 4]), Assert::equalTo(1));
        } else {
            TestCase::assertThat($this->col->count(['bar' => 4]), Assert::equalTo(0));
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

        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'Kekse']), Assert::equalTo(2));
        TestCase::assertThat($this->col->count(['bar' => 1, 'foo' => 'bar']), Assert::equalTo(0));
        TestCase::assertThat($this->col->count(['bar' => 2]), Assert::equalTo(1));
        TestCase::assertThat($this->col->count(['bar' => 3, 'foo' => 'Kekse']), Assert::equalTo(1));
        TestCase::assertThat($this->col->count(['bar' => 3, 'foo' => 'yoo']), Assert::equalTo(0));
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

        TestCase::assertThat(count($result), Assert::equalTo(3));
        TestCase::assertThat($result[0]['bar'], Assert::equalTo(1));
        TestCase::assertThat($result[1]['bar'], Assert::equalTo(2));
        TestCase::assertThat($result[2]['bar'], Assert::equalTo(3));
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

        TestCase::assertThat(count($result), Assert::equalTo(3));
        TestCase::assertThat($result[0]['bar'], Assert::equalTo(3));
        TestCase::assertThat($result[1]['bar'], Assert::equalTo(2));
        TestCase::assertThat($result[2]['bar'], Assert::equalTo(1));
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

        TestCase::assertThat(count($result), Assert::equalTo(4));
        TestCase::assertThat($result[0]['bar'], Assert::equalTo(1));
        TestCase::assertThat($result[1]['bar'], Assert::equalTo(2));
        TestCase::assertThat($result[1]['foo'], Assert::equalTo('zab'));
        TestCase::assertThat($result[2]['bar'], Assert::equalTo(2));
        TestCase::assertThat($result[2]['foo'], Assert::equalTo('baz'));
        TestCase::assertThat($result[3]['bar'], Assert::equalTo(3));
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

        TestCase::assertThat(count($result), Assert::equalTo(2));
        TestCase::assertThat($result[0]['bar'], Assert::equalTo(2));
        TestCase::assertThat($result[1]['bar'], Assert::equalTo(3));
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

        TestCase::assertThat(count($result), Assert::equalTo(2));
        TestCase::assertThat($result[0]['bar'], Assert::equalTo(1));
        TestCase::assertThat($result[1]['bar'], Assert::equalTo(2));
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

        TestCase::assertThat(count($result), Assert::equalTo(1));
        TestCase::assertThat($result[0]['foo'], Assert::equalTo('bar'));
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
            'foo' => 'bar'
        ]);
        $result = iterator_to_array($result);

        TestCase::assertThat(count($result), Assert::equalTo(1));
        TestCase::assertThat($result[0]['foo'], Assert::equalTo('bar'));
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
        TestCase::assertThat(count($result), Assert::equalTo(3));

        $result = $this->col->find([
            'krypton' => '$exists'
        ]);
        $result = iterator_to_array($result);
        TestCase::assertThat(count($result), Assert::equalTo(1));
        TestCase::assertThat($result[0]['foo'], Assert::equalTo('foo'));

        $result = $this->col->find([
            'inexistant' => '$exists'
        ]);
        $result = iterator_to_array($result);
        TestCase::assertThat(count($result), Assert::equalTo(0));
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

        TestCase::assertThat($result['foo'], Assert::equalTo('baz'));
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

        TestCase::assertThat($result['foo'], Assert::equalTo('foo'));
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

        TestCase::assertThat($result['foo'], Assert::equalTo('#'));
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

        TestCase::assertThat($result, Assert::equalTo(null));
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
        TestCase::assertThat($result['foo'], Assert::equalTo('foo'));
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
        TestCase::assertThat($result, Assert::equalTo(null));
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
        TestCase::assertThat($result, Assert::isInstanceOf(MockCursor::class));
        $result = $result->toArray();
        TestCase::assertThat(count($result), Assert::equalTo(2));
        TestCase::assertThat($result[0]['foo'], Assert::equalTo('foo'));
        TestCase::assertThat($result[1]['foo'], Assert::equalTo('baz'));
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

        $result = $this->col->find([
            '$or' => [
                ['$and' => [['foo' => 'foo'], ['bar' => '3']]],
                ['$and' => [['foo' => 'baz'], ['bar' => '2']]],
            ]
        ]);

        TestCase::assertThat($result, Assert::isInstanceOf(MockCursor::class));
        $result = $result->toArray();
        TestCase::assertThat(count($result), Assert::equalTo(2));
        TestCase::assertThat($result[0]['foo'], Assert::equalTo('foo'));
        TestCase::assertThat($result[1]['foo'], Assert::equalTo('baz'));
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

        $result = $this->col->find([
            '$and' => [
                ['$or' => [['foo' => 1], ['foo' => 'foo']]],
                ['$or' => [['bar' => 'foo'], ['bar' => 3]]],
            ]
        ]);

        TestCase::assertThat($result, Assert::isInstanceOf(MockCursor::class));
        $result = $result->toArray();
        TestCase::assertThat(count($result), Assert::equalTo(1));
        TestCase::assertThat($result[0]['foo'], Assert::equalTo('foo'));
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
        TestCase::assertThat($result['foo'], Assert::equalTo('foo'));
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

        TestCase::assertThat($result, Assert::isInstanceOf(MockCursor::class));
        $result = $result->toArray();
        TestCase::assertThat(count($result), Assert::equalTo(3));
        TestCase::assertThat($result[0]['foo'], Assert::equalTo('for'));
        TestCase::assertThat($result[1]['foo'], Assert::equalTo('foo'));
        TestCase::assertThat($result[2]['foo'], Assert::equalTo('bar'));
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

        $result = $this->col->find([
            '$or' => [
                ['bar' => ['$lte' => 1]],
                ['bar' => ['$gte' => 3]]
            ]
        ]);

        TestCase::assertThat($result, Assert::isInstanceOf(MockCursor::class));
        $result = $result->toArray();
        TestCase::assertThat(count($result), Assert::equalTo(3));
        TestCase::assertThat($result[0]['foo'], Assert::equalTo('for'));
        TestCase::assertThat($result[1]['foo'], Assert::equalTo('foo'));
        TestCase::assertThat($result[2]['foo'], Assert::equalTo('bar'));
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

        TestCase::assertThat($result, Assert::isInstanceOf(MockCursor::class));
        $result = $result->toArray();
        TestCase::assertThat(count($result), Assert::equalTo(1));
        TestCase::assertThat($result[0]['foo'], Assert::equalTo('foo'));
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

        TestCase::assertThat($result, Assert::isInstanceOf(MockCursor::class));
        $result = $result->toArray();
        TestCase::assertThat(count($result), Assert::equalTo(3));
        TestCase::assertThat($result[0]['foo'], Assert::equalTo('foo'));
        TestCase::assertThat($result[1]['foo'], Assert::equalTo('bar'));
        TestCase::assertThat($result[2]['foo'], Assert::equalTo('baz'));
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

        TestCase::assertThat($result, Assert::isNull());
    }

    public function testCollectionGetName()
    {
        $col = new MockCollection('foo');
        TestCase::assertThat($col->getCollectionName(), Assert::equalTo('foo'));
    }

    public function testCreateIndexRegistersIndex()
    {
        $col = new MockCollection('foo');
        $col->createIndex('foo', ['unique' => true]);
        $col->createIndex('bar');

        $indices = iterator_to_array($col->listIndexes());

        TestCase::assertThat(count($indices), Assert::equalTo(2));

        $first = $indices[0];
        $second = $indices[1];

        TestCase::assertThat($first['unique'], Assert::isTrue());
        TestCase::assertThat($second['unique'], Assert::isFalse());
        TestCase::assertThat($first['key'], Assert::equalTo('foo'));
    }

    public function testCreateIndexRegistersMultifieldIndex()
    {
        $col = new MockCollection('foo');
        $col->createIndex(['foo' => 1, 'bar' => -1], ['unique' => true]);

        $indices = iterator_to_array($col->listIndexes());

        TestCase::assertThat(count($indices), Assert::equalTo(1));

        $first = $indices[0];

        TestCase::assertThat($first['unique'], Assert::isTrue());
        TestCase::assertThat($first['key'], Assert::equalTo(['foo' => 1, 'bar' => -1]));
        TestCase::assertThat($first['name'], Assert::equalTo('foo_1_bar_1'));
    }

}
