<?php
namespace Helmich\MongoMock;

use Helmich\MongoMock\Log\Index;
use Helmich\MongoMock\Log\Query;
use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectID;
use MongoDB\Collection;
use MongoDB\Model\BSONDocument;

/**
 * A mocked MongoDB collection
 *
 * This class mimicks the behaviour of a MongoDB collection (and also extends
 * the actual `MongoDB\Collection` class and can be used as a drop-in
 * replacement). All operations are performed in-memory and are not persisted.
 *
 * NOTE: This class is not complete! Many methods are missing and I will only
 * implement them as soon as I need them. Feel free to open an issue or (better)
 * a pull request if you need something.
 *
 * @package Helmich\MongoMock
 */
class MockCollection extends Collection
{
    public $queries = [];
    public $documents = [];
    public $indices = [];
    public $dropped = false;

    /** @var string */
    private $name;

    /**
     * @param string $name
     */
    public function __construct(string $name = 'collection')
    {
        $this->name = $name;
    }

    public function insertOne($document, array $options = [])
    {
        if (!isset($document['_id'])) {
            $document['_id'] = new ObjectID();
        }

        if (!$document instanceof BSONDocument) {
            $document = new BSONDocument($document);
        }

        $document = new BSONDocument($document);
        $this->documents[] = $document;

        return new MockInsertOneResult($document['_id']);
    }

    public function insertMany(array $documents, array $options = [])
    {
        $insertedIds = array_map(function($doc) use ($options) {
            return $this->insertOne($doc, $options)->getInsertedId();
        }, $documents);

        return new MockInsertManyResult($insertedIds);
    }

    public function deleteMany($filter, array $options = [])
    {
        $matcher = $this->matcherFromQuery($filter);
        foreach ($this->documents as $i => $doc) {
            if ($matcher($doc)) {
                unset($this->documents[$i]);
            }
        }
        $this->documents = array_values($this->documents);
    }

    public function updateOne($filter, $update, array $options = [])
    {
        $matcher = $this->matcherFromQuery($filter);
        foreach ($this->documents as $i => &$doc) {
            if ($matcher($doc)) {
                foreach ($update['$set'] ?? [] as $k => $v) {
                    $doc[$k] = $v;
                }
                return;
            }
        }
    }

    public function updateMany($filter, $update, array $options = [])
    {
        $matcher = $this->matcherFromQuery($filter);
        foreach ($this->documents as $i => &$doc) {
            if (!$matcher($doc)) {
                continue;
            }

            foreach ($update['$set'] ?? [] as $k => $v) {
                $doc[$k] = $v;
            }
        }
    }

    public function find($filter = [], array $options = [])
    {
        // record query for future assertions
        $this->queries[] = new Query($filter, $options);

        $matcher = $this->matcherFromQuery($filter);
        $skip = $options['skip'] ?? 0;

        $collectionCopy = array_values($this->documents);
        if (isset($options['sort'])) {
            usort($collectionCopy, function($a, $b) use ($options): int {
                foreach($options['sort'] as $key => $dir) {
                    $av = $a[$key];
                    $bv = $b[$key];

                    if (is_object($av)) {
                        $av = "" . $av;
                    }
                    if (is_object($bv)) {
                        $bv = "" . $bv;
                    }

                    if ($av > $bv) {
                        return $dir;
                    } else if ($av < $bv) {
                        return -$dir;
                    }
                }
                return 0;
            });
        }

        return call_user_func(function() use ($collectionCopy, $matcher, $skip) {
            foreach ($collectionCopy as $doc) {
                if ($matcher($doc)) {
                    if ($skip-- > 0) {
                        continue;
                    }
                    yield($doc);
                }
            }
        });

    }

    public function findOne($filter = [], array $options = [])
    {
        $results = $this->find($filter, $options);
        foreach ($results as $result) {
            return $result;
        }
        return null;
    }

    public function count($filter = [], array $options = [])
    {
        $count = 0;
        $matcher = $this->matcherFromQuery($filter);
        foreach ($this->documents as $i => $doc) {
            if ($matcher($doc)) {
                $count ++;
            }
        }
        return $count;
    }

    public function createIndex($key, array $options = [])
    {
        $this->indices[] = new Index($key, $options);
    }

    public function drop(array $options = [])
    {
        $this->documents = [];
        $this->dropped = true;
    }

    public function aggregate(array $pipeline, array $options = [])
    {
        // TODO: Implement this function
    }

    public function bulkWrite(array $operations, array $options = [])
    {
        // TODO: Implement this function
    }

    public function createIndexes(array $indexes)
    {
        foreach ($indexes as $index) {
            $key = $index['key'];
            unset($index['key']);
            $this->createIndex($key, $index);
        }
    }

    public function deleteOne($filter, array $options = [])
    {
        $matcher = $this->matcherFromQuery($filter);
        foreach ($this->documents as $i => $doc) {
            if ($matcher($doc)) {
                unset($this->documents[$i]);
                $this->documents = array_values($this->documents);
                return;
            }
        }
    }

    public function distinct($fieldName, $filter = [], array $options = [])
    {
        // TODO: Implement this function
    }

    public function dropIndex($indexName, array $options = [])
    {
        // TODO: Implement this function
    }

    public function dropIndexes(array $options = [])
    {
        // TODO: Implement this function
    }

    public function findOneAndDelete($filter, array $options = [])
    {
        // TODO: Implement this function
    }

    public function findOneAndReplace($filter, $replacement, array $options = [])
    {
        // TODO: Implement this function
    }

    public function findOneAndUpdate($filter, $update, array $options = [])
    {
        // TODO: Implement this function
    }

    public function getCollectionName()
    {
        // TODO: Implement this function
    }

    public function getDatabaseName()
    {
        // TODO: Implement this function
    }

    public function getNamespace()
    {
        // TODO: Implement this function
    }

    public function listIndexes(array $options = [])
    {
        // TODO: Implement this function
    }

    public function replaceOne($filter, $replacement, array $options = [])
    {
        // TODO: Implement this function
    }

    public function withOptions(array $options = [])
    {
        // TODO: Implement this function
    }

    private function matcherFromQuery(array $query): callable
    {
        $matchers = [];

        foreach ($query as $field => $constraint) {
            $matchers[$field] = $this->matcherFromConstraint($constraint);
        }

        return function($doc) use ($matchers): bool {
            foreach ($matchers as $field => $matcher) {
                if (!$matcher($doc[$field])) {
                    return false;
                }
            }
            return true;
        };
    }

    private function matcherFromConstraint($constraint): callable
    {
        if (is_callable($constraint)) {
            return $constraint;
        }

        if ($constraint instanceof \PHPUnit_Framework_Constraint) {
            return function($val) use ($constraint): bool {
                return $constraint->evaluate($val, '', true);
            };
        }

        if ($constraint instanceof ObjectID) {
            return function($val) use ($constraint): bool {
                return ("" . $constraint) == ("" . $val);
            };
        }

        if (is_array($constraint)) {
            return function($val) use ($constraint): bool {
                $result = true;
                foreach ($constraint as $type => $operand) {
                    switch ($type) {
                        // Mongo operators (subset)
                        case '$lt':
                            $result = $result && ($val < $operand);
                            break;
                        case '$lte':
                            $result = $result && ($val <= $operand);
                            break;

                        // Custom operators
                        case '$instanceOf':
                            $result = $result && is_a($val, $operand);
                    }
                }
                return $result;
            };
        }

        return function($val) use ($constraint): bool {
            if ($val instanceof Binary && is_string($constraint)) {
                return $val->getData() == $constraint;
            }

            return $val == $constraint;
        };
    }
}