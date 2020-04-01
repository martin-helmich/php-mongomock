<?php

namespace Helmich\MongoMock\Assert;

use Helmich\MongoMock\Log\Query;
use Helmich\MongoMock\MockCollection;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\Constraint;

class QueryWasExecutedConstraint extends Constraint
{

    /** @var array */
    private $filter;

    /** @var array */
    private $options;

    public function __construct($filter, $options = [])
    {
        $this->filter = $filter;
        $this->options = $options;
    }

    protected function matches($other): bool
    {
        if (!$other instanceof MockCollection) {
            return false;
        }

        $constraint = Assert::equalTo(new Query($this->filter, $this->options));

        foreach ($other->queries as $query) {
            if ($constraint->evaluate($query, '', true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a string representation of the object.
     *
     * @return string
     */
    public function toString(): string 
    {
        return 'executed query by ' . json_encode($this->filter);
    }
}