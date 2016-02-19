<?php
namespace Helmich\MongoMock;

use Helmich\MongoMock\Assert\QueryWasExecutedConstraint;

class Assert
{
    public static function executedQuery($filter, $options = [])
    {
        return new QueryWasExecutedConstraint($filter, $options);
    }
}