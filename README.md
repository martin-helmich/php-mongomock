# MongoDB mock classes

## Author and license

Martin Helmich
This library is [MIT-licenced](LICENSE.txt).

## Synopsis and motivation

This class contains an implementation of the `MongoDB/Collection` class that
can store, modify and filter documents in memory, together with a set of
(optional) PHPUnit assertions.

I wrote this library because I wanted to unit-test a library that used MongoDB
collections intensively and felt that mocking the `MongoDB/Collection` class
using PHPUnit's built-in mock builders was too restrictive.

**Note**: Currently, this implementation contains only a subset of the actual
MongoDB collection API. I've only implemented the parts of the API that I needed
for my use case. If you need additional functionality, feel free to open an
issue, or (better yet) a pull request.

## Usage

You can use this class exactly as you'd use the `MongoDB/Collection` class
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