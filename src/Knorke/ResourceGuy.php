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
     * Creates a full copy of the ResourceGuy instance as well as sub guys, if available.
     *
     * @return array
     */
    public function getArrayCopy() : array
    {
        $copy = array();

        foreach ($this->propertyValues as $key => $value) {
            if ($value instanceof ResourceGuy) {
                $copy[$key] = $value->getArrayCopy();
            } elseif (is_array($value)) {
                $copy[$key] = array();
                foreach ($value as $subKey => $subValue) {
                    if ($subValue instanceof ResourceGuy) {
                        $copy[$key][] = $subValue->getArrayCopy();
                    } else {
                        $copy[$key][] = $subValue;
                    }
                }
            } else {
                $copy[$key] = $value;
            }
        }

        return $copy;
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

    /**
     * @param string|int $key
     * @return bool
     */
    public function offsetExists($key) : bool
    {
        return null !== $this->offsetGet($key);
    }

    /**
     * @param string|int $key
     * @return null|ResourceGuy|Node|array
     */
    public function offsetGet($key)
    {
        if (isset($this->propertyValues[$key])) {
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

            // not found
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
            throw new KnorkeException('Only arrays, ResourceGuy or Node instances are allows to store here: '. json_encode($value));
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

    public function reset()
    {
        $this->rewind();
        $this->propertyValues = array();
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
