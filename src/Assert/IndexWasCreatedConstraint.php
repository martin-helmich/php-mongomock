<?php

namespace Helmich\MongoMock\Assert;

use Helmich\MongoMock\Log\Index;
use Helmich\MongoMock\MockCollection;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\Constraint;

class IndexWasCreatedConstraint extends Constraint
{

    /**
     * @var
     */
    private $key;
    /**
     * @var array
     */
    private $options;

    public function __construct($key, $options = [])
    {
        $this->key = $key;
        $this->options = $options;
    }

    protected function matches($other): bool
    {
        if (!$other instanceof MockCollection) {
            return false;
        }

        $constraint = Assert::equalTo(new Index($this->key, $this->options));

        foreach ($other->indices as $index) {
            if ($constraint->evaluate($index, '', true)) {
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
        return 'has index of ' . json_encode($this->key);
    }
}