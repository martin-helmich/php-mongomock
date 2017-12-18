<?php

namespace Helmich\MongoMock;

use MongoDB\InsertOneResult;

class MockInsertOneResult extends InsertOneResult
{
    private $insertedId;

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct($insertedId)
    {
        $this->insertedId = $insertedId;
    }

    public function getInsertedCount()
    {
        return 1;
    }

    public function getInsertedId()
    {
        return $this->insertedId;
    }

    public function isAcknowledged()
    {
        return true;
    }

}