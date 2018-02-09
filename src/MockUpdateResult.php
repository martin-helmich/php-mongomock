<?php
namespace Helmich\MongoMock;

use MongoDB\UpdateResult;

class MockUpdateResult extends UpdateResult
{
    private $matched;
    private $modified;
    private $upsertedIds;

    /**
     * @param int $matched
     * @param int $modified
     * @param array $upsertedIds
     */
    public function __construct($matched=0, $modified=0, array $upsertedIds=[])
    {
        $this->matched = $matched;
        $this->modified = $matched;
        $this->upsertedIds = $upsertedIds;
    }

    public function getMatchedCount()
    {
        return $this->matched;
    }

    public function getModifiedCount()
    {
        return $this->modified;
    }

    public function getUpsertedCount()
    {
        return count($this->upsertedIds);
    }

    public function getUpsertedId()
    {
        return $this->upsertedIds;
    }

    public function isAcknowledged()
    {
        return true;
    }

}
