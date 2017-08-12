<?php
namespace Helmich\MongoMock;
 
use \Iterator;

class MockCursor implements Iterator
{
    protected $store = [];
    protected $position = 0;

    public function __construct(array $documents=[]) 
    {
        $this->store = $documents;
    }

    public function toArray()
    {
        return $this->store;
    }

    function rewind() {
        $this->position = 0;
    }

    function current() {
        return $this->store[$this->position];
    }

    function key() {
        return $this->position;
    }

    function next() {
        ++$this->position;
    }

    function valid() {
        return isset($this->store[$this->position]);
    }
}
