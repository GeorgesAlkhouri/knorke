<?php

namespace Knorke\Database;

use Knorke\Database\QueryHandler;

class MemoryQueryHandler extends QueryHandler
{
    protected $connectionParameter = array();
    protected $memory = array();
    protected $result = null;

    public function prepare(array $transitionInfo, array $connectionParameter)
    {
        $this->connectionParameter = $connectionParameter;
        $this->memory = array();
    }

    public function sendQuery(array $transitionInfo, $query)
    {
        // string means, get me entries from key $query
        if (is_string($query)) {
            $this->result = $this->memory[$query];
        // array means, store this
        } elseif (is_array($query)) {
            $this->memory[$query['key']] = $query['values'];
        } else {
            var_dump($query);
            throw new \Exception('Invalid query.');
        }
    }
}
