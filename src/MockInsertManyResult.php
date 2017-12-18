<?php

namespace Helmich\MongoMock;

use MongoDB\InsertManyResult;

class MockInsertManyResult extends InsertManyResult
{
    private $insertedIds;

    /**
     * @param array $insertedIds
     */
    public function __construct(array $insertedIds)
    {
        $this->insertedIds = $insertedIds;
    }

    public function getInsertedCount()
    {
        return count($this->insertedIds);
    }

    public function getInsertedIds()
    {
        return $this->insertedIds;
    }

    public function isAcknowledged()
    {
        return true;
    }

}