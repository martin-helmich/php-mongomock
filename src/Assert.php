<?php

namespace Helmich\MongoMock;

use Helmich\MongoMock\Assert\HasDocumentConstraint;
use Helmich\MongoMock\Assert\IndexWasCreatedConstraint;
use Helmich\MongoMock\Assert\QueryWasExecutedConstraint;
use PHPUnit\Framework\Constraint as Constraint;

/**
 * Helper class containing static factory methods
 *
 * @package Helmich\MongoMock
 */
class Assert
{
    /**
     * Asserts that a given query was executed.
     *
     * @param array $filter  A MongoDB query object
     * @param array $options Search options
     * @return Constraint
     */
    public static function collectionExecutedQuery(array $filter, array $options = []): Constraint
    {
        return new QueryWasExecutedConstraint($filter, $options);
    }

    /**
     * Asserts that an index was created for a collection
     *
     * @param array $key     The index keys
     * @param array $options Index options
     * @return Constraint
     */
    public static function collectionHasIndex(array $key, array $options = []): Constraint
    {
        return new IndexWasCreatedConstraint($key, $options);
    }

    /**
     * Asserts that a document matching the search filter exists in a collection
     *
     * @param array $filter A MongoDB query object
     * @return Constraint
     */
    public static function collectionHasDocument(array $filter = []): Constraint
    {
        return new HasDocumentConstraint($filter);
    }
}