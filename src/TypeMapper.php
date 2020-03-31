<?php

namespace Helmich\MongoMock;

use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;

class TypeMapper {

    /** @const array */
    const DEFAULT_TYPE_MAP = [
        'array' => BSONArray::class,
        'document' => BSONDocument::class,
        'root' => BSONDocument::class
    ];

    /** @var array */
    private $typeMap;

    /**
     * @param array $typeMapDefaultOverwrite
     * @return TypeMapper
     */
    static function createWithDefault (array $typeMapDefaultOverwrite = []) : TypeMapper
    {
        return new static(array_merge(static::DEFAULT_TYPE_MAP, $typeMapDefaultOverwrite));
    }

    /**
     * @param array $typeMap
     */
    function __construct (array $typeMap)
    {
        $this->typeMap = $typeMap;
    }

    /**
     * Merges a TypeMap with another TypeMap and returns a new mapper instance
     *
     * @param TypeMapper $typeMapper
     * @return TypeMapper
     */
    function mergeWith(TypeMapper $typeMapper) : TypeMapper
    {
        $instance = clone $this;
        $instance->typeMap = array_merge($instance->typeMap, $typeMapper->typeMap);

        return $instance;
    }

    /**
     * @param BSONDocument $document
     * @return BSONDocument|array
     */
    function map (BSONDocument $document) : iterable
    {
        /** @var BSONDocument $document */
        $document = $this->typeMapRecursively($document);

        if ($this->typeMap['document'] === 'array') {
            $document = $document->getArrayCopy();
        } elseif ($this->typeMap['document'] !== BSONDocument::class) {
            $document = new $this->typeMap['document']($document->getArrayCopy());
        }

        return $document;
    }

    private function typeMapRecursively ($document)
    {
        foreach ($document as $key => &$value) {
            if (is_array($value) && $this->typeMap['array'] !== 'array') {
                $value = $this->typeMapRecursively($value);
                $value = new $this->typeMap['array']($value);
            }
        }

        return $document;
    }

}