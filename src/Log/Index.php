<?php

namespace Helmich\MongoMock\Log;

class Index
{
    private $key;
    /**
     * @var array
     */
    private $options;

    public function __construct($key, array $options = [])
    {
        $this->key = $key;
        $this->options = $options;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function getOptions()
    {
        return $this->options;
    }
}
