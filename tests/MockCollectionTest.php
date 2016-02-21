<?php
namespace Helmich\MongoMock\Tests;

use Helmich\MongoMock\MockCollection;
use MongoDB\BSON\ObjectID;
use MongoDB\Collection;
use MongoDB\InsertManyResult;
use MongoDB\InsertOneResult;
use MongoDB\Model\BSONDocument;

class MockCollectionTest extends \PHPUnit_Framework_TestCase
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

        assertThat($result, isInstanceOf(InsertOneResult::class));
        assertThat($result->getInsertedCount(), equalTo(1));
        assertThat($result->getInsertedId(), isInstanceOf(ObjectID::class));
        assertThat($result->isAcknowledged(), isTrue());

        $find = $this->col->findOne(['_id' => $result->getInsertedId()]);

        assertThat($find, logicalNot(isNull()));
        assertThat($find['foo'], equalTo('bar'));
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
        $this->col->updateOne(['bar' => 1], ['$set' => ['foo' => 'Kekse']]);

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
    public function testUpdateManyUpdatesManyObjects()
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
    public function testFindWorksWithCallableOperators()
    {
        $this->col->insertMany([
            ['foo' => 'foo', 'bar' => 3],
            ['foo' => 'bar', 'bar' => 1],
            ['foo' => 'baz', 'bar' => 2],
        ]);
        $result = $this->col->find([
            'foo' => function($var): bool {
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
}