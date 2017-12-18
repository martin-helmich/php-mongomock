<?php

namespace Helmich\MongoMock\Assert;

use Helmich\MongoMock\Log\Query;
use Helmich\MongoMock\MockCollection;

class QueryWasExecutedConstraint extends \PHPUnit_Framework_Constraint
{

    /** @var array */
    private $filter;

    /** @var array */
    private $options;

    public function __construct($filter, $options = [])
    {
        parent::__construct();
        $this->filter = $filter;
        $this->options = $options;
    }

    protected function matches($other)
    {
        if (!$other instanceof MockCollection) {
            return false;
        }

        $constraint = \PHPUnit_Framework_Assert::equalTo(new Query($this->filter, $this->options));

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
    public function toString()
    {
        return 'executed query by ' . json_encode($this->filter);
    }
}