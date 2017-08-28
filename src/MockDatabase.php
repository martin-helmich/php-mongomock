<?php
namespace Helmich\MongoMock;

use MongoDB\Collection;
use MongoDB\Database;

/**
 * A mocked MongoDB database
 *
 * This class mimicks the behaviour of a MongoDB database (and also extends
 * the actual `MongoDB\Database` class and can be used as a drop-in
 * replacement). All operations are performed in-memory and are not persisted.
 *
 * NOTE: This class is not complete! Many methods are missing and I will only
 * implement them as soon as I need them. Feel free to open an issue or (better)
 * a pull request if you need something.
 *
 * @package Helmich\MongoMock
 */
class MockDatabase extends Database
{
    /** @var string */
    private $name;

    /** @var Collection **/
    private $collections = [];

    /**
     * @param string $name
     */
    public function __construct(string $name = 'database')
    {
        $this->name = $name;
    }

    /**
     * Return the database name.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }
        
    /**
     * Return collection
     * 
     * @param  string $name
     * @return Collection
     */
    public function __get($name)
    {
        return $this->selectCollection($name);
    }
    
    /**
     * Return collection
     * 
     * @param  string $name
     * @param  array  $options
     * @return Collection
     */    
    public function selectCollection($name, array $options = [])
    {
        if (!isset($this->collections[$name])) {
            $this->collections[$name] = new MockCollection($name, $this);
        }
        return $this->collections[$name];
    }
}
