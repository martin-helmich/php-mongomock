<?php
namespace Helmich\MongoMock;

use MongoDB\DeleteResult;

class MockDeleteResult extends DeleteResult
{
    private $matched;
    private $modified;
    private $deletedIds;

    /**
     * @param int $matched
     * @param int $modified
     * @param array $deletedIds
     */
    public function __construct($matched=0, $modified=0, array $deletedIds=[])
    {
        $this->matched = $matched;
        $this->modified = $modified;
        $this->deletedIds = $deletedIds;
    }

    public function getMatchedCount()
    {
        return $this->matched;
    }

    public function getModifiedCount()
    {
        return $this->modified;
    }

    public function getDeletedCount()
    {
        return count($this->deletedIds);
    }

    public function getDeletedIds()
    {
        return $this->deletedIds;
    }

    public function isAcknowledged()
    {
        return true;
    }
}
