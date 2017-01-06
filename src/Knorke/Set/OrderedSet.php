<?php

namespace Knorke\Set;

class OrderedSet implements \Iterator
{
    protected $set = array();
    protected $index = 0;

    public function __construct(array $startSet = array())
    {
        // sort by key
        ksort($startSet);
        foreach ($startSet as $key => $value) {
            $this->add($value);
        }
    }

    public function add($element)
    {
        $this->set[$this->index] = $element;

        ++$this->index;
    }

    public function current()
    {
        return $this->set[$this->index];
    }

    /**
     * @todo TODO
     */
    public function drop($key)
    {
        // drop

        // shrink array so and compute keys accordingly
    }

    public function next()
    {
        ++$this->index;
    }

    public function key()
    {
        return $this->index;
    }

    public function valid()
    {
        return isset($this->set[$this->key()]);
    }

    public function rewind()
    {
        $this->index = 0;
    }
}
