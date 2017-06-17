<?php

namespace Knorke;

use Knorke\Exception\KnorkeException;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\NamedNode;
use Saft\Rdf\Node;
use Saft\Rdf\RdfHelpers;
use Saft\Rdf\StatementIterator;
use Saft\Sparql\Result\SetResult;
use Saft\Store\Store;

/**
 * This class maps a given result of a certain resource, class, ... to an instance of itself. With that you
 * are able to access the property values the same way as you use an array.
 *
 * For instance:
 * Lets assume you have the following SetResult.
 *
 * object(Saft\Sparql\Result\SetResultImpl)
 *
 * array (size=2)
 *      'p' =>
 *        object(Saft\Rdf\NamedNodeImpl)
 *          protected 'uri' => string 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'
 *      'o' =>
 *        object(Saft\Rdf\NamedNodeImpl)
 *          protected 'uri' => string 'http://www.w3.org/2002/07/owl#Class'
 *
 * Mapping that to an instance of Blank, will lead to:
 *
 *      $blank['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] = 'http://www.w3.org/2002/07/owl#Class';
 *
 * To do that just call:
 *
 *      $blank = new Knorke\DataBlank($setResult, 'p', 'o');
 *      $blank['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] = 'http://www.w3.org/2002/07/owl#Class';
 *
 * Or even with namespaces:
 *
 *      $blank['rdf:type'] = 'owl:Class';
 */
class DataBlank implements \ArrayAccess, \Iterator
{
    /**
     * @var CommonNamespaces
     */
    protected $commonNamespaces;

    protected $data = array();

    protected $graphs = array();

    protected $options = array();

    protected $position = 0;

    /**
     * @var RdfHelpers
     */
    protected $rdfHelpers;

    /**
     * @var Store
     */
    protected $store;

    /**
     * @param CommonNamespaces $commonNamespaces
     * @param RdfHelpers $rdfHelpers
     * @param Store $store
     * @param array $graphs
     * @param array $options
     */
    public function __construct(
        CommonNamespaces $commonNamespaces,
        RdfHelpers $rdfHelpers,
        Store $store,
        array $graphs,
        array $options = array()
    ) {
        $this->commonNamespaces = $commonNamespaces;
        $this->graphs = $graphs;
        $this->rdfHelpers = $rdfHelpers;
        $this->store = $store;
        $this->options = array_merge(
            array(
                'add_internal_data_fields' => true, // if set to true, fields like _idUri gets created
                'use_prefixed_predicates' => true,
                'use_prefixed_objects' => true,
            ),
            $options
        );
    }

    /**
     * @param array $graphs Array of NamedNode instances
     */
    protected function buildGraphsList(array $graphs) : string
    {
        $fromGraphList = array();

        foreach ($graphs as $graph) {
            $fromGraphList[] = 'FROM <'. $graph->getUri(). '>';
        }

        return implode(' ', $fromGraphList);
    }

    /**
     * @return int
     */
    public function count() : int
    {
        return count($this->data);
    }

    public function current()
    {
        return array_values($this->data)[$this->position];
    }

    /**
     * @return array Array copy of this instance.
     */
    public function getArrayCopy() : array
    {
        $copy = array();

        foreach ($this->data as $key => $value) {
            // if entry is of type DataBlank call getArrayCopyRecursive recursively
            if (is_array($value) || $value instanceof DataBlank) {
                $copy[$key] = $this->getArrayCopyRecursive($value);
            } elseif (is_string($key) && $key !== $value) {
                $copy[$key] = $value;
            } else {
                $copy[] = $value;
            }
        }

        return $copy;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    protected function getArrayCopyRecursive($value)
    {
        // sub array found
        if (is_array($value)) {
            $copy = array();
            foreach ($value as $subkey => $entry) {
                if (is_array($entry) || $entry instanceof DataBlank) {
                    // $copy[$subkey] = $this->getArrayCopyRecursive($entry);
                    $entry = $this->getArrayCopyRecursive($entry);
                } elseif (is_string($subkey) && $subkey !== $entry) {
                    $copy[$subkey] = $entry;
                } else {
                }
                $copy[] = $entry;
            }
            return $copy;

        // datablank found
        } elseif ($value instanceof DataBlank) {
            return $value->getArrayCopy();
        }

        return $value;
    }

    /**
     * @return Traversable
     */
    public function getIterator() : \Traversable
    {
        return new \ArrayIterator($this->data);
    }

    /**
     * @param Node $node
     * @return mixed
     */
    protected function getNodeValue(Node $node)
    {
        // named node
        if ($node->isNamed()) {
            return $node->getUri();
        // literal
        } elseif ($node->isLiteral()) {
            return $node->getValue();
        // blank node
        } elseif ($node->isBlank()) {
            return $node->toNQuads();
        }

        return null;
    }

    public function key()
    {
        return array_keys($this->data)[$this->position];
    }

    /**
     * Inits this instance be searching for ?p ?o of its own _idUri.
     *
     * @throws KnorkeException if _idUri is not set.
     */
    public function initBySelfSearch()
    {
        if (isset($this->data['_idUri'])) {
            $resourceId = $this->data['_idUri'];
            unset($this->data['_idUri']);
            $this->initByStoreSearch($resourceId);
        } else {
            throw new KnorkeException('Cant init, because _idUri is not set.');
        }
    }

    /**
     * @param string $resourceId
     */
    public function initByStoreSearch(string $resourceId)
    {
        /*
         * check parameter $resourceId
         */
        if (!$this->rdfHelpers->simpleCheckURI($resourceId)
            && !$this->rdfHelpers->simpleCheckBlankNodeId($resourceId)) {
            throw new KnorkeException('Invalid $resourceId given (must be an URI or blank node): '. $resourceId);
        }

        // set internal _idUri field
        if ($this->options['add_internal_data_fields']) {
            $this->offsetSet('_idUri', $resourceId);
        }

        $graphFromList = $this->buildGraphsList($this->graphs);

        $resourceId = $this->commonNamespaces->extendUri($resourceId);

        // get direct neighbours of $resourceId
        $result = $this->store->query('SELECT * '. $graphFromList .' WHERE {<'. $resourceId .'> ?p ?o .}');

        // go through neighbours
        foreach ($result as $entry) {
            $p = $entry['p'];
            $o = $entry['o'];

            /*
             * predicate value
             */
            $predicateValue = $this->options['use_prefixed_predicates']
                ? $this->commonNamespaces->shortenUri($p->getUri())
                : $this->commonNamespaces->extendUri($p->getUri());

            /*
             * object is URI (check for more triples behind it)
             */
            if ($o->isNamed()) {
                $value = $this->options['use_prefixed_objects']
                    ? $this->commonNamespaces->shortenUri($o->getUri())
                    : $this->commonNamespaces->extendUri($o->getUri());

            } elseif ($o->isLiteral()) {
                $value = $this->getNodeValue($o);
            }

            // set property key and object value
            $this->offsetSet($predicateValue, $value);
        }
    }

    public function next()
    {
        ++$this->position;
    }

    /**
     * @param mixed $key
     */
    public function offsetExists($key)
    {
        return true === isset($this->data[$key]);
    }

    /**
     * @param mixed $key
     */
    public function offsetGet($key)
    {
        // if set, return value
        if (isset($this->data[$key])) {
            return $this->data[$key];

        // if not set, check if it points to an URI. if so, try load it
        } elseif (isset($this->data['_idUri']) && $this->rdfHelpers->simpleCheckURI($key)) {
            $predicateUri = $this->commonNamespaces->extendUri($key);

            $graphFromList = $this->buildGraphsList($this->graphs);

            $result = $this->store->query('SELECT * '. $graphFromList .' WHERE {
                <'. $this->data['_idUri'] .'> <'. $predicateUri .'> ?o .
            }');

            foreach ($result as $entry) {
                if ($this->rdfHelpers->simpleCheckURI($entry['o'])) {
                    $dataBlank = new DataBlank(
                        $this->commonNamespaces,
                        $this->rdfHelpers,
                        $this->store,
                        $this->graphs
                    );
                    $dataBlank->initByStoreSearch($entry['o']);
                    $this->offsetSet($key, $dataBlank);

                    return $this->offsetGet($key);

                // assume a literal
                } elseif (false === $this->rdfsHelpers->simpleCheckBlankNodeId($entry['o'])) {
                    $this->offsetSet($key, $entry['o']);
                    return $this->offsetGet($key);
                }
                // blank nodes will be ignored
            }

        } else {
            throw new KnorkeException('No data found for key: '. $key);
        }
    }

    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function offsetSet($key, $value)
    {
        if (!isset($this->data[$key])) {
            // if $value is an URI, prepare for further loads
            if ($this->rdfHelpers->simpleCheckURI($key)
                && is_string($value) && $this->rdfHelpers->simpleCheckURI($value)) {
                $this->data[$key] = new DataBlank($this->commonNamespaces, $this->rdfHelpers, $this->store, $this->graphs);
                $this->data[$key]->offsetSet('_idUri', $value);
            } else {
                $this->data[$key] = $value;
            }

        } else {
            // if datablank instance found, put it into a list
            if ($this->data[$key] instanceof DataBlank) {
                // create array of DataBlank instances
                if ($this->rdfHelpers->simpleCheckURI($key) && $this->rdfHelpers->simpleCheckURI($value)) {
                    $blank = new DataBlank($this->commonNamespaces, $this->rdfHelpers, $this->store, $this->graphs);
                    $blank->offsetSet('_idUri', $value);

                    $this->data[$key] = array(
                        0 => $this->data[$key],
                        1 => $blank
                    );
                }

            // extend list
            } elseif (is_array($this->data[$key])) {
                // if value is an URI, add DataBlank instance
                if ($this->rdfHelpers->simpleCheckURI($value)) {
                    $blank = new DataBlank($this->commonNamespaces, $this->rdfHelpers, $this->store, $this->graphs);
                    $blank->offsetSet('_idUri', $value);
                    $value = $blank;
                }

                $this->data[$key][] = $value;

            // assume only a single value was stored before, so put it and the new guy into an array
            } else {
                $this->data[$key] = array(
                    0 => $this->data[$key],
                    1 => $value
                );
            }
        }
    }

    /**
     * @param mixed $key
     */
    public function offsetUnset($key)
    {
        unset($this->data[$key]);
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function valid()
    {
        return isset(array_values($this->data)[$this->position]);
    }

    /**
     * @return string
     */
    public function __toString() : string
    {
        return json_encode($this->getArrayCopy());
    }
}
