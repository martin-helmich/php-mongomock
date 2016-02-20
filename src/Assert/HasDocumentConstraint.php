<?php
namespace Helmich\MongoMock\Assert;

use Helmich\MongoMock\Log\Index;
use Helmich\MongoMock\MockCollection;

class HasDocumentConstraint extends \PHPUnit_Framework_Constraint
{

    /**
     * @var
     */
    private $filter;

    public function __construct($filter)
    {
        parent::__construct();
        $this->filter = $filter;
    }

    protected function matches($other)
    {
        if (!$other instanceof MockCollection) {
            return false;
        }

        return $other->count($this->filter) > 0;
    }

    /**
     * Returns a string representation of the object.
     *
     * @return string
     */
    public function toString()
    {
        return 'has document that matches ' . json_encode($this->filter);
    }
}