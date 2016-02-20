<?php
namespace Helmich\MongoMock;

use MongoDB\Collection;
use MongoDB\Model\BSONDocument;

class MockCollection extends Collection
{
    public $queries = [];

    public $documents = [];
    public $indices = [];

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct()
    {

    }

    public function insertOne($document, array $options = [])
    {
        $document = new BSONDocument($document);
        $this->documents[] = $document;
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
        $matcher = $this->matcherFromQuery($filter);
        foreach ($this->documents as $doc) {
            if ($matcher($doc)) {
                return $doc;
            }
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
        $this->indices[] = ['key' => $key, 'options' => $options];
    }

    public function hasIndex($key, array $options = [])
    {
        foreach ($this->indices as $index) {
            if ($index == ['key' => $key, 'options' => $options]) {
                return true;
            }
        }
        return false;
    }

    private function matcherFromQuery(array $query)
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

    private function matcherFromConstraint($constraint)
    {
        if (is_callable($constraint)) {
            return $constraint;
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
            };
        }

        return function($val) use ($constraint): bool {
            return $val == $constraint;
        };
    }
}