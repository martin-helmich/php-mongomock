<?php

namespace Helmich\MongoMock;

use ArrayIterator;
use MongoDB\Model\CollectionInfoIterator;
/**
 * Implementation of CollectionInfoIterator 
 * in case MongoDB\Model\CollectionInfoLegacyIterator is
 * not available
 * 
 * @package Helmich\MongoMock
 */
class MockCollectionInfoIterator implements CollectionInfoIterator {
    private $arrayIterator;

    public function __construct(ArrayIterator $arrayIterator) {
        $this->arrayIterator = $arrayIterator;
    }

    public function rewind() : void{
        $this->arrayIterator->rewind();
    }

    public function current() {
        return $this->arrayIterator->current();
    }

    public function key() {
        return $this->arrayIterator->key();
    }

    public function next() : void {
        $this->arrayIterator->next();
    }

    public function valid() : bool {
        return $this->arrayIterator->valid();
    }
}
