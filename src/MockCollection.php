<?php

namespace Helmich\MongoMock;

use ArrayIterator;
use Helmich\MongoMock\Log\Index;
use Helmich\MongoMock\Log\Query;
use MongoDB\BSON;
use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\Regex;
use MongoDB\Collection;
use MongoDB\Driver\Exception\RuntimeException as DriverRuntimeException;
use MongoDB\Model\BSONDocument;
use MongoDB\Model\BSONArray;
use MongoDB\Model\IndexInfoIteratorIterator;
use MongoDB\Operation\FindOneAndUpdate;
use PHPUnit\Framework\Constraint\Constraint;

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
    const TYPE_BSON = [
        5 => BSON\Binary::class,
        128 => BSON\Decimal128::class,
        13 => BSON\JavaScript::class,
        127 => BSON\MaxKey::class,
        -1 => BSON\MinKey::class,
        7 => BSON\ObjectId::class,
        11 => BSON\Regex::class,
        17 => BSON\Timestamp::class,
        9 => BSON\UTCDateTime::class
    ];

    const TYPE = [
        1 => 'double',
        2 => 'string',
        3 => 'object',
        4 => 'array',
        8 => 'boolean',
        10 => 'NULL',
        16 => 'integer',
        18 => 'integer'
    ];

    public $queries = [];
    public $documents = [];
    public $indices = [];
    public $dropped = false;

    /** @var string */
    private $name;

    /** @var MockDatabase|null */
    private $db;

    /** @var array */
    private $options = [];

    /** @var TypeMapper */
    private $typeMapper;

    /**
     * @param string $name
     * @param MockDatabase $db
     */
    public function __construct(string $name = 'collection', MockDatabase $db = null, array $options = [])
    {
        $this->name = $name;
        $this->db = $db;
        $this->options = $options;

        if ($db !== null) {
            $this->options = array_merge($db->getOptions(), $options);
        } else {
            $this->options = $options;
        }

        $this->typeMapper = TypeMapper::createWithDefault($this->options['typeMap'] ?? []);
    }

    public function insertOne($document, array $options = [])
    {
        if (!isset($document['_id'])) {
            $document['_id'] = new ObjectID();
        } else {
            // make sure document with the same id does not exist
            foreach ($this->documents as $doc) {
                // if document with the same id already exists
                if ($doc['_id'] == $document['_id']) {
                    throw new DriverRuntimeException();
                }
            }
        }

        if (!$document instanceof BSONDocument) {
            $document = new BSONDocument($document);
        }

        // Possible double instantiation of BSONDocument?
        // With or without the below, it seems that the
        // BSONDocument class recurses automatically.
        // e.g. $document->bsonSerialize() will give the same result
        // so I couldn't write a test to capture the need or not for this.
        // I'm commenting it out anyway.
        // $document = new BSONDocument($document);

        $this->documents[] = $document;

        return new MockInsertOneResult($document['_id']);
    }

    public function insertMany(array $documents, array $options = [])
    {
        $insertedIds = array_map(function ($doc) use ($options) {
            return $this->insertOne($doc, $options)->getInsertedId();
        }, $documents);

        return new MockInsertManyResult($insertedIds);
    }

    public function deleteMany($filter, array $options = [])
    {
        $matcher = $this->matcherFromQuery($filter);
        $count = 0;
        foreach ($this->documents as $i => $doc) {
            if ($matcher($doc)) {
                unset($this->documents[$i]);
                $count++;
            }
        }
        $this->documents = array_values($this->documents);
        return new MockDeleteResult($count, $count);
    }

    public function updateOne($filter, $update, array $options = [])
    {
        $matcher = $this->matcherFromQuery($filter);
        foreach ($this->documents as $i => &$doc) {
            if ($matcher($doc)) {
                $this->updateCore($doc, $update);
                return new MockUpdateResult(1, 1);
            }
        }

        $result = $this->updateUpsert($filter, $update, $options, false);
        $upserted = $result === null ? [] : $result->getInsertedIds();
        return new MockUpdateResult(0, 0, $upserted);
    }

    public function updateMany($filter, $update, array $options = [])
    {
        $matcher = $this->matcherFromQuery($filter);
        $matched = 0;
        foreach ($this->documents as $i => &$doc) {
            if (!$matcher($doc)) {
                continue;
            }

            $this->updateCore($doc, $update);
            $matched++;
        }

        $result = $this->updateUpsert($filter, $update, $options, $matched !== 0);
        $upserted = $result === null ? [] : $result->getInsertedIds();
        return new MockUpdateResult($matched, $matched, $upserted);
    }

    private function updateUpsert($filter, $update, $options, $anyUpdates)
    {
        if (array_key_exists('upsert', $options)) {
            if ($options['upsert'] && !$anyUpdates) {
                $set = $update['$set'] ?? [];
                $setOnInsert = $update['$setOnInsert'] ?? [];
                $commonFields = array_intersect_key($set, $setOnInsert);

                //Emulate Mongo behaviour
                //@link https://stackoverflow.com/questions/23992723/findandmodify-fails-with-error-cannot-update-field1-and-field1-at-the-same/23994425#23994425
                if(!empty($commonFields)){
                    throw new Exception('Operators $setOnInsert and $set cannot have the same field(s): '.
                        implode(', ', array_keys($commonFields)));
                }
                if (!empty($set) || !empty($setOnInsert)) {
                    $documents = [array_merge($filter, $set, $setOnInsert)];
                } else {
                    $documents = [$update];
                }
                $this->insertMany($documents, $options);
            }
        }
    }

    private function updateCore(&$doc, $update)
    {
        // The update operators are required, as exemplified here:
        // http://mongodb.github.io/mongo-php-library/tutorial/crud/
        $supported = ['$set', '$unset', '$inc', '$push', '$setOnInsert'];
        $unsupported = array_diff(array_keys($update), $supported);
        if (count($unsupported) > 0) {
            throw new Exception("Unsupported update operators found: " . implode(', ', $unsupported));
        }

        foreach(['$set', '$setOnInsert'] as $operator){
            foreach ($update[$operator] ?? [] as $k => $v) {
                $dot = strpos($k, ".");
                if ($dot !== false) {
                    $tmp = &$doc;
                    $keys = explode(".", $k);
                    if ($keys !== null) {
                        foreach ($keys as $key) {
                            $tmp = &$tmp[$key];
                        }
                        $tmp = $v;
                    }
                } else {
                    $doc[$k] = $v;
                }
            }
        }

        foreach ($update['$unset'] ?? [] as $k => $v) {
            if (isset($doc[$k])) {
                unset($doc[$k]);
            }
        }

        foreach ($update['$inc'] ?? [] as $k => $v) {
            //Mongo behaviour: it increments even a non-existing field
            if(!isset($doc[$k])){
                $doc[$k] = 0;
            }
            if (is_integer($v) && is_integer($doc[$k])) {
                $doc[$k] += $v;
            }
        }

        foreach ($update['$push'] ?? [] as $k => $v) {
            if (isset($doc[$k]) && is_array($doc[$k])) {
                $doc[$k][] = $v;
            }
        }
    }

    public function find($filter = [], array $options = []): MockCursor
    {
        $typeMapper = $this->typeMapper;
        if (isset($options['typeMap'])) {
            $typeMapper = $typeMapper->mergeWith(new TypeMapper($options['typeMap']));
        }

        // record query for future assertions
        $this->queries[] = new Query($filter, $options);

        $matcher = $this->matcherFromQuery($filter);
        $skip = $options['skip'] ?? 0;

        $collectionCopy = array_values($this->documents);

        if (isset($options['sort'])) {
            usort($collectionCopy, function ($a, $b) use ($options): int {
                foreach ($options['sort'] as $key => $dir) {
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
                    } else {
                        if ($av < $bv) {
                            return -$dir;
                        }
                    }
                }
                return 0;
            });
        }

        if (isset($options['limit'])) {
            $limit = $options['limit'] !== 0 ? $options['limit'] : null;
        }

        $cursor = [];
        foreach ($collectionCopy as $doc) {
            if ($matcher($doc)) {
                if ($skip-- > 0) {
                    continue;
                }
                if (isset($limit)) {
                    if ($limit === 0) {
                        break;
                    }
                    $limit--;
                }

                $cursor[] = $typeMapper->map($doc);
            }
        }

        return new MockCursor($cursor);
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
                $count++;
            }
        }
        return $count;
    }

    public function countDocuments($filter = [], array $options = [])
    {
        return $this->count($filter, $options);
    }

    public function createIndex($key, array $options = [])
    {
        $name = '';
        if (is_string($key)) {
            $name = $key . '_1';
        } elseif (is_array($key)) {
            foreach ($key as $field => $enabled) {
                if (strlen($name) !== 0) {
                    $name .= '_';
                }

                $name .= $field . '_1';
            }
        }

        $this->indices[$name] = new Index($key, $options);
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

    public function createIndexes(array $indexes, array $options = [])
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
        $count = 0;
        $deletedIds = [];
        foreach ($this->documents as $i => $doc) {
            if ($matcher($doc)) {
                $deletedIds[] = $doc['_id'];
                unset($this->documents[$i]);
                $this->documents = array_values($this->documents);
                $count++;
                break;
            }
        }
        return new MockDeleteResult($count, $count, $deletedIds);
    }

    public function distinct($fieldName, $filter = [], array $options = [])
    {
        $values = [];

        $matcher = $this->matcherFromQuery($filter);
        foreach ($this->documents as $document) {
            if ($matcher($document) && isset($document[$fieldName])) {
                $values[] = $document[$fieldName];
            }
        }
        return array_unique($values);
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
        if (!isset($options['returnDocument'])) {
            // Standard behaviour according to https://docs.mongodb.com/php-library/v1.2/reference/method/MongoDBCollection-findOneAndUpdate/#definition
            $options['returnDocument'] = FindOneAndUpdate::RETURN_DOCUMENT_BEFORE;
        }

        $returnDocument = null;
        if ($options['returnDocument'] === FindOneAndUpdate::RETURN_DOCUMENT_BEFORE) {
            $returnDocument = $this->findOne($filter, $options);
        }

        $this->updateOne($filter, $update, $options);

        if ($options['returnDocument'] === FindOneAndUpdate::RETURN_DOCUMENT_AFTER) {
            return $this->findOne($filter, $options);
        } elseif ($returnDocument !== null) {
            return $returnDocument;
        }

        throw new Exception('Given option value "' . $options['returnDocument'] . '" for findOneAndUpdate() "returnDocument" option is invalid');
    }

    public function getCollectionName()
    {
        return $this->name;
    }

    public function getDatabaseName()
    {
        if ($this->db === null) {
            throw new Exception('database required to call getDatabaseName()');
        } else {
            return (string)$this->db;
        }
    }

    public function getNamespace()
    {
        // TODO: Implement this function
    }

    public function listIndexes(array $options = [])
    {
        $indices = [];
        $dbName = $this->db ? $this->db->getDatabaseName() : "unknown";

        foreach ($this->indices as $name => $index) {
            $indices[] = [
                'v' => 1,
                'unique' => isset($index->getOptions()['unique']) ? $index->getOptions()['unique'] : false,
                'key' => $index->getKey(),
                'name' => $name,
                'ns' => $dbName . '.' . $this->name,
            ];
        }

        return new IndexInfoIteratorIterator(new ArrayIterator($indices));
    }

    public function replaceOne($filter, $replacement, array $options = [])
    {
        // TODO: Implement this function
    }

    public function withOptions(array $options = [])
    {
        // TODO: Implement this function
    }

    private function buildRecursiveMatcherQuery(array $query): array
    {
        $matchers = [];
        foreach ($query as $field => $value) {
            if ($field === '$and' || $field === '$or' || $field === '$nor' || is_numeric($field)) {
                $matchers[$field] = $this->buildRecursiveMatcherQuery($value);
            } else {
                $matchers[$field] = $this->matcherFromConstraint($value);
            }
        }
        return $matchers;
    }

    private function matcherFromQuery(array $query): callable
    {
        $matchers = $this->buildRecursiveMatcherQuery($query);
        $orig_matchers = $matchers;
        return $is_match = function ($doc, $compare = null) use (&$is_match, &$matchers, $orig_matchers): bool {
            if ($compare === null) {
                $matchers = $orig_matchers;
            }

            foreach ($matchers as $field => $matcher) {
                if ($field === '$and') {
                    if (!is_array($matcher) || count($matcher) === 0) {
                        throw new Exception('$and expression must be a nonempty array');
                    }

                    foreach ($matcher as $sub) {
                        $matchers = $sub;
                        if (!$is_match($doc, $field)) {
                            return false;
                        }
                    }

                    return true;
                } elseif ($field === '$or') {
                    if (!is_array($matcher) || count($matcher) === 0) {
                        throw new Exception('$or expression must be a nonempty array');
                    }

                    foreach ($matcher as $sub) {
                        $matchers = $sub;
                        if ($is_match($doc, $field)) {
                            return true;
                        }
                    }

                    return false;
                } elseif ($field === '$nor') {
                    if (!is_array($matcher) || count($matcher) === 0) {
                        throw new Exception('$nor expression must be a nonempty array');
                    }

                    foreach ($matcher as $sub) {
                        $matchers = $sub;
                        if ($is_match($doc, $field)) {
                            return false;
                        }
                    }
                    return true;
                } elseif ($field === '$isolated') {
                    return true;
                } else {
                    // needed for case of $exists query filter and field is inexistant
                    $val = $this->getArrayValue($doc, $field);
                    if (!$matcher($val)) {
                        return false;
                    }
                }
            }
            return true;
        };
    }

    private function getArrayValue($array, string $path, string $separator = '.')
    {
        if (isset($array[$path])) {
            return $array[$path];
        }

        $keys = explode($separator, $path);

        foreach ($keys as $key) {
            if (!isset($array[$key])) {
                //needed for case of $exists query filter and field is inexistant
                return null;
            }

            $array = $array[$key];
        }

        return $array;
    }


    private function matcherFromConstraint($constraint): callable
    {
        if (is_callable($constraint)) {
            return $constraint;
        }

        if ($constraint instanceof Constraint) {
            return function ($val) use ($constraint): bool {
                return $constraint->evaluate($val, '', true);
            };
        }

        if ($constraint instanceof ObjectID) {
            return function ($val) use ($constraint): bool {
                return ("" . $constraint) == ("" . $val);
            };
        }

        if ($constraint instanceof Regex) {
            return function ($val) use ($constraint): bool {
                $pattern = str_replace('#', '\\#', $constraint->getPattern());
                return preg_match('#' . $pattern . '#' . $constraint->getFlags(), $val);
            };
        }

        if (is_array($constraint)) {
            return $match = function ($val) use (&$constraint, &$match): bool {
                //cast $val to array if it is an instance of BSONArray
                //this will prevent is_array,array_reduce... from failing
                if ($val instanceof BSONArray) {
                    $val = (array)$val;
                }
                $result = true;
                foreach ($constraint as $type => $operand) {
                    //cast $operand to array if it is an instance of BSONArray
                    //this will prevent is_array,array_reduce... from failing
                    if ($operand instanceof BSONArray) {
                        $operand = (array)$operand;
                    }
                    switch ($type) {
                        // Mongo operators (subset)
                        case '$gt':
                            $result = ($val > $operand);
                            break;
                        case '$gte':
                            $result = ($val >= $operand);
                            break;
                        case '$lt':
                            $result = ($val < $operand);
                            break;
                        case '$lte':
                            $result = ($val <= $operand);
                            break;
                        case '$eq':
                            $result = ($val === $operand);
                            break;
                        case '$ne':
                            $result = ($val != $operand);
                            break;
                        case '$in':
                            $matchInArray = function ($val, $operand) {
                                return array_reduce($operand, function ($ac, $op) use ($val) {
                                    if ($op instanceof \MongoDB\BSON\Regex) {
                                        $regex = "/" . $op->getPattern() . "/" . $op->getFlags();
                                        return ($ac || preg_match($regex, $val) === 1);
                                    } else if (is_string($op)) {
                                        if (@preg_match($op, '') === false) {
                                            return ($ac || $op == $val);
                                        }
                                        return ($ac || preg_match($op, $val) === 1);
                                    }
                                    return ($ac || $op == $val);
                                }, false);
                            };
                            $result = (!is_array($val))
                                ? $matchInArray($val, $operand)
                                : array_reduce(
                                    $val,
                                    function ($acc, $fval) use ($operand, $matchInArray) {
                                        return ($acc || $matchInArray($fval, $operand));
                                    },
                                    false
                                );
                            break;
                        case '$elemMatch':
                            if (is_array($val)) {
                                $matcher = $this->matcherFromQuery($operand);
                                foreach ($val as $v) {
                                    $result = $matcher($v);
                                    if ($result === true) {
                                        break;
                                    }
                                }
                            } else {
                                $constraint = $operand;
                                $result = $match($val);
                            }
                            break;
                        case '$exists':
                            // note that for inexistant fields, val is overridden to be null
                            return $operand ? $val !== null : $val === null;
                            break;
                        case '$size':
                            $result = count($val) === $operand;
                            break;
                        case '$type':
                            $result = $this->compareType($operand, $val);
                            break;
                        case '$not':
                            if (is_array($operand)) {
                                $matcher = $this->matcherFromConstraint($operand);
                                $result = !$matcher($val);
                            } else {
                                $result = !$operand;
                            }
                            break;
                        case '$regex':
                            if ($operand instanceof Regex) {
                                $regex = "/" . $operand->getPattern() . "/" . $operand->getFlags();
                                $result = preg_match($regex, $val) === 1;
                            } else if (is_string($operand)) {
                                if (@preg_match($operand, '') === false) {
                                    throw new Exception("Invalid constraint for operator '" . $type . "'");
                                }
                                $result = preg_match($operand, $val) === 1;
                            } else {
                                throw new Exception("Invalid constraint for operator '" . $type . "'");
                            }
                            break;
                        case '$all':
                            $result = false;
                            if (is_array($val)) {
                                if (count($operand) == 1 && is_array($operand[0])) {
                                    $result = count($val) == count($operand[0]) && array_reduce(
                                        $operand[0],
                                        function ($acc, $op) use ($val) {
                                            return $acc && in_array($op, $val, true);
                                        },
                                        true
                                    );
                                }
                                $result = $result || array_reduce($operand, function ($acc, $op) use ($val) {
                                    return $acc && in_array($op, $val, true);
                                }, true);
                            }
                            break;
                            // Custom operators
                        case '$instanceOf':
                            $result = is_a($val, $operand);
                            break;

                        default:
                            throw new Exception("Constraint operator '" . $type . "' not yet implemented in MockCollection");
                    }

                    if (!$result) {
                        break;
                    }
                }
                return $result;
            };
        }

        return function ($val) use ($constraint): bool {
            if (is_string($constraint) && $constraint == '$exists') {
                // note that for inexistant fields, val is overridden to be null
                return !is_null($val);
            }

            if ($val instanceof Binary && is_string($constraint)) {
                return $val->getData() == $constraint;
            }

            if (is_array($val) && is_scalar($constraint)) {
                return in_array($constraint, $val, true);
            }

            return $val == $constraint;
        };
    }

    protected function compareType(int $type, $value): bool
    {
        if ($value instanceof BSON\Type) {
            return isset(self::TYPE_BSON[$type]) && is_a($value, self::TYPE_BSON[$type]);
        } else {
            return isset(self::TYPE[$type]) && gettype($value) === self::TYPE[$type];
        }
    }
}
