<?php

use PHPUnit_Framework_Constraint as Constraint;

function collectionExecutedQuery(...$params): Constraint
{
    return \Helmich\MongoMock\Assert::collectionExecutedQuery(...$params);
}

function collectionHasIndex(...$params): Constraint
{
    return \Helmich\MongoMock\Assert::collectionHasIndex(...$params);
}

function collectionHasDocument(...$params): Constraint
{
    return \Helmich\MongoMock\Assert::collectionHasDocument(...$params);
}