<?php

namespace Helmich\MongoMock\Log;

class Query
{
    private $filter;
    /**
     * @var array
     */
    private $options;

    public function __construct($filter, $options = [])
    {
        $this->filter = $filter;
        $this->options = $options;
    }
}