<?php
namespace Helmich\MongoMock;

use Helmich\MongoMock\Assert\HasDocumentConstraint;
use Helmich\MongoMock\Assert\IndexWasCreatedConstraint;
use Helmich\MongoMock\Assert\QueryWasExecutedConstraint;
use PHPUnit_Framework_Constraint as Constraint;

class Assert
{
    public static function collectionExecutedQuery(array $filter, array $options = []): Constraint
    {
        return new QueryWasExecutedConstraint($filter, $options);
    }

    public static function collectionHasIndex(array $key, array $options = []): Constraint
    {
        return new IndexWasCreatedConstraint($key, $options);
    }

    public static function collectionHasDocument(array $filter = []): Constraint
    {
        return new HasDocumentConstraint($filter);
    }
}