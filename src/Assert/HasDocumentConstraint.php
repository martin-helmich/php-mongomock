<?php

namespace Helmich\MongoMock\Assert;

use Helmich\MongoMock\MockCollection;
use PHPUnit\Framework\Constraint\Constraint;

class HasDocumentConstraint extends Constraint
{

    /**
     * @var
     */
    private $filter;

    public function __construct($filter)
    {
        $this->filter = $filter;
    }

    protected function matches($other): bool
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
    public function toString(): string
    {
        return 'has document that matches ' . json_encode($this->filter);
    }
}