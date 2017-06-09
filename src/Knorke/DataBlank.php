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
class DataBlank extends \ArrayObject
{
    /**
     * @var CommonNamespaces
     */
    protected $commonNamespaces;

    protected $data = array();

    /**
     * @var array
     */
    protected $options;

    /**
     * @var RdfHelpers
     */
    protected $rdfHelpers;

    /**
     * @param CommonNamespaces $commonNamespaces
     * @param RdfHelpers $rdfHelpers
     * @param array $options optional, default=array()
     */
    public function __construct(
        CommonNamespaces $commonNamespaces,
        RdfHelpers $rdfHelpers,
        array $options = array()
    ) {
        $this->commonNamespaces = $commonNamespaces;
        $this->rdfHelpers = $rdfHelpers;
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
     * @return int
     */
    public function count() : int
    {
        return count($this->data);
    }

    /**
     * Helper function to allow content gathering by using full URIs or prefixed ones.
     * If the indirect way worked, the previously try $property will be applied to.
     *
     * @param string|number $property
     * @return mixed
     */
    public function get($property)
    {
        // found? ok, return back!
        if (isset($this->data[$property])) {
            return $this->data[$property];
        } else {
            // if not found, try the prefixed version resp. the full URI
            if (false !== strpos($property, 'http://')) { // full URI
                $shortendProperty = $this->commonNamespaces->shortenUri($property);
                if (isset($this->data[$shortendProperty])) {
                    $this->data[$property] = $this->data[$shortendProperty];
                    return $this->data[$shortendProperty];
                }
            } else { // shorted version
                $extendedProperty = $this->commonNamespaces->extendUri($property);
                if (isset($this->data[$extendedProperty])) {
                    $this->data[$property] = $this->data[$extendedProperty];
                    return $this->data[$extendedProperty];
                }
            }
        }

        return null;
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

    /**
     * @param Store $store
     * @param NamedNode $graph
     * @param string $resourceId
     * @param string $parentPredicate Optional, only if $resourceId is a blank node
     * @param string $parentSubject Optional, only if $resourceId is a blank node
     */
    public function initByStoreSearch(
        Store $store,
        NamedNode $graph,
        string $resourceId,
        string $parentPredicate = null,
        string $parentSubject = null
    ) {
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

        /*
         * Parameter $resourceId is an URI
         */
        if ($this->rdfHelpers->simpleCheckURI($resourceId)) {
            $resourceId = $this->commonNamespaces->extendUri($resourceId);

            // get direct neighbours of $resourceId
            $result = $store->query('SELECT * FROM <'. $graph .'> WHERE {<'. $resourceId .'> ?p ?o .}');

        /*
         * Parameter $resourceId is a blank node
         *
         * we assume, that we are in a recursion and this function was called before with
         * resourceId as URI
         */
        } else {
            // get direct neighbours of related blank node (?blank)
            $result = $store->query('SELECT * FROM <'. $graph .'> WHERE {
                <'. $parentSubject .'> <'. $parentPredicate .'> ?blank .
                ?blank ?p ?o .
            }');
        }

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

                /*
                 * check if behind the URI are more triples
                 */
                $dataBlank = new DataBlank($this->commonNamespaces, $this->rdfHelpers);
                $dataBlank->initByStoreSearch($store, $graph, $o->getUri());

                // if more than one data entry is in the data blank, we use it
                // if not, it only contains an _idUri value and is worthless
                if (1 < count($dataBlank)) {
                    $value = $dataBlank;
                }

            /*
             * object is blank node (check for more triples behind it)
             */
            } elseif ($o->isBlank()) {
                $dataBlank = new DataBlank($this->commonNamespaces, $this->rdfHelpers);
                $dataBlank->initByStoreSearch($store, $graph, $o->toNQuads(), $p->getUri(), $resourceId);

                // if more than one data entry is in the data blank, we use it
                // if not, it only contains an _idUri value and is worthless
                if (1 < count($dataBlank)) {
                    $value = $dataBlank;
                }

            } else {
                $value = $this->getNodeValue($o);
            }

            // set property key and object value
            $this->offsetSet($predicateValue, $value);
        }
    }

    /**
     * @param mixed $key
     */
    public function offsetExists($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * @param mixed $key
     */
    public function offsetGet($key)
    {
        return $this->data[$key];
    }

    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function offsetSet($key, $value)
    {
        // value already set, but is not the same we already stored
        if (isset($this->data[$key]) && $this->data[$key] !== $value) {
            // is already an array, add further item
            if (is_array($this->data[$key])) {
                array_push($this->data[$key], $value);
            // if current entry is set but its not an array, make it to array
            } else {
                $this->data[$key] = array(0 => $this->data[$key], 1 => $value);
            }
        // value not set already
        } else {
            $this->data[$key] = $value;
        }
    }

    /**
     * @param mixed $key
     */
    public function offsetUnset($key)
    {
        unset($this->data[$key]);
    }

    /**
     * @return string
     */
    public function __toString() : string
    {
        return json_encode($this->getArrayCopy());
    }
}
