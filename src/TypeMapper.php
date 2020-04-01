<?php

namespace Helmich\MongoMock;

use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;

class TypeMapper
{

    /** @const array */
    const DEFAULT_TYPE_MAP = [
        'array'    => BSONArray::class,
        'document' => BSONDocument::class,
        'root'     => BSONDocument::class,
    ];

    /** @var array */
    private $typeMap;

    /**
     * @param array $typeMapDefaultOverwrite
     * @return TypeMapper
     */
    static function createWithDefault(array $typeMapDefaultOverwrite = []): TypeMapper
    {
        return new static(array_merge(static::DEFAULT_TYPE_MAP, $typeMapDefaultOverwrite));
    }

    /**
     * @param array $typeMap
     */
    function __construct(array $typeMap)
    {
        $this->typeMap = $typeMap;
    }

    /**
     * Merges a TypeMap with another TypeMap and returns a new mapper instance
     *
     * @param TypeMapper $typeMapper
     * @return TypeMapper
     */
    function mergeWith(TypeMapper $typeMapper): TypeMapper
    {
        $instance          = clone $this;
        $instance->typeMap = array_merge($instance->typeMap, $typeMapper->typeMap);

        return $instance;
    }

    /**
     * @param BSONDocument $document
     * @return BSONDocument|array
     */
    function map(BSONDocument $document): iterable
    {
        /** @var BSONDocument $document */
        $document = $this->typeMapRecursively($document);

        return $this->typeMapDocument($document);
    }

    /**
     * @param mixed $document
     * @return mixed
     */
    private function typeMapRecursively($document)
    {
        foreach ($document as $key => &$value) {
            if (is_array($value)) {
                $value = $this->typeMapArray($value);
            }
        }

        return $document;
    }

    /**
     * @param array $value
     * @return array|object
     */
    private function typeMapArray(array $value)
    {
        // If the list is not indexed numerically, it is an associative array
        // Treat associative arrays as documents
        if (array_keys($value) !== range(0, count($value) - 1)) {
            return $this->typeMapDocument($this->typeMapRecursively($value));
        }

        if ($this->typeMap['array'] === 'array') {
            return $value;
        }

        return new $this->typeMap['array']($this->typeMapRecursively($value));
    }

    /**
     * @param BSONDocument|array|object $document
     * @return array|object
     */
    private function typeMapDocument($document)
    {
        if ($document instanceof BSONDocument) {
            $document = $document->getArrayCopy();
        }

        if ($this->typeMap['document'] === 'array') {
            return $document;
        }

        return new $this->typeMap['document']($document);
    }

}