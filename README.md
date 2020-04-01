# MongoDB mock classes

![Unit tests](https://github.com/martin-helmich/php-mongomock/workflows/Unit%20tests/badge.svg)

## Author and license

Martin Helmich  
This library is [MIT-licenced](LICENSE.txt).

## Synopsis and motivation

This class contains implementations of the [MongoDB\Collection][mongodb-collection] and
[MongoDB\Database][mongodb-database] classes (not to be confused with the [Mongo\Collection][mongo-collection]
class from the deprecated _mongo_ extension)
that can store, modify and filter documents in memory, together with a set of
(optional) PHPUnit assertions.

I wrote this library because I wanted to unit-test a library that used MongoDB
collections intensively and felt that mocking the `MongoDB\Collection` class
using PHPUnit's built-in mock builders was too restrictive.

**Note**: Currently, this implementation contains only a subset of the actual
MongoDB collection API. I've only implemented the parts of the API that I needed
for my use case. If you need additional functionality, feel free to open an
issue, or (better yet) a pull request.

## Installation

    $ composer require --dev helmich/mongomock

## Compatibility

There are several release branches of this library, each of these being compatible with different releases of PHPUnit and PHP. The following table should give an easy overview:

| "Mongomock" version | PHPUnit 4 | PHPUnit 5 | PHPUnit 6 | PHPUnit 7 | PHPUnit 8 |
| ------------------------ | --------- | --------- | --------- | --------- | --------- |
| v1 (branch `v1`), **unsupported** | :no_entry_sign: | :white_check_mark: | :no_entry_sign: | :no_entry_sign: | :no_entry_sign: |
| v2 (branch `master`) | :no_entry_sign: | :no_entry_sign: | :white_check_mark: | :white_check_mark: | :white_check_mark: |

When you are using `composer require` and have already declared a dependency to `phpunit/phpunit` in your `composer.json` file, Composer should pick latest compatible version automatically.

## Usage

You can use this library exactly as you'd use the `MongoDB\Collection` or `MongoDB\Database` classes
(in theory, at least -- remember, this package is not API-complete):

```php
use Helmich\MongoMock\MockCollection;

$collection = new MockCollection();
$collection->createIndex(['foo' => 1]);

$documentId = $collection->insertOne(['foo' => 'bar'])->insertedId();
$collection->updateOne(['_id' => $documentId], ['$set' => ['foo' => 'baz']]);
```

## Differences

In some aspects, the `MongoDB\Collection`'s API was extended to allow for better
testability:

1.  Filter operands may contain callback functions that are applied to document
    properties:

    ```php
    $r = $collection->find([
        'someProperty' => function($p) {
            return $p == 'bar';
        }
    ]);
    ```

2.  Filter operands may contain PHPUnit constraints (meaning instances of the
    `PHPUnit_Framework_Constraint` class). You can easily build these using the
    factory functions in the `PHPUnit_Framework_Assert` class.

    ```php
    $r = $collection->find([
        'someProperty' => \PHPUnit_Framework_Assert::isInstanceOf(\MongoDB\BSON\Binary::class)
    ]);
    ```

## Testing

To run the tests (anywhere with a running Docker installation):

```
$ docker-compose run phpunit
```

[mongo-collection]: http://php.net/manual/en/class.mongocollection.php
[mongodb-collection]: https://docs.mongodb.com/php-library/master/reference/class/MongoDBCollection/
[mongodb-database]: https://docs.mongodb.com/php-library/master/reference/class/MongoDBDatabase/
