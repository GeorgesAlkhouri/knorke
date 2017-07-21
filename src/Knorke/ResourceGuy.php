<?php

namespace Knorke;

use Knorke\Exception\KnorkeException;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\Node;

class ResourceGuy implements \ArrayAccess, \Iterator, \Countable
{
    protected $commonNamespaces;

    /**
     * @var array
     */
    protected $propertyValues;

    protected $position;

    public function __construct(CommonNamespaces $commonNamespaces)
    {
        $this->commonNamespaces = $commonNamespaces;

        $this->position = 0;

        $this->propertyValues = array();
    }

    /**
     * @return int
     */
    public function count() : int
    {
        return count($this->propertyValues);
    }

    public function current()
    {
        return array_values($this->propertyValues)[$this->position];
    }

    /**
     * @return int|string
     */
    public function key()
    {
        return array_keys($this->propertyValues)[$this->position];
    }

    public function next()
    {
        ++$this->position;
    }

    public function offsetExists($key) : bool
    {
        return isset($this->propertyValues[$key]);
    }

    /**
     * @return null|ResourceGuy|Node|array
     */
    public function offsetGet($key)
    {
        if ($this->offsetExists($key)) {
            return $this->propertyValues[$key];
        } else {
            // extended $key
            $extendedKey = $this->commonNamespaces->extendUri($key);

            // shortened $key
            $shortenedKey = $this->commonNamespaces->shortenUri($key);

            if (isset($this->propertyValues[$extendedKey])) {
                return $this->propertyValues[$extendedKey];
            } elseif (isset($this->propertyValues[$shortenedKey])) {
                return $this->propertyValues[$shortenedKey];
            } else {
                return null;
            }
        }
    }

    public function offsetSet($key, $value)
    {
        if (is_array($value) || $value instanceof ResourceGuy || $value instanceof Node) {
            $this->propertyValues[$key] = $value;
        } else {
            throw new KnorkeException('Only arrays, ResourceGuy or Node instances are allows to store here.');
        }
    }

    public function offsetUnset($key)
    {
        unset($this->propertyValues[$key]);
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function valid()
    {
        return isset(array_values($this->propertyValues)[$this->position]);
    }

    /**
     * @return string
     */
    public function __toString() : string
    {
        return json_encode($this->propertyValues);
    }
}
