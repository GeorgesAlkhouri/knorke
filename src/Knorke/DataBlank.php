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
     * Helper function to allow content gathering by using full URIs or prefixed ones.
     * If the indirect way worked, the previously try $property will be applied to.
     *
     * @param string|number $property
     * @return mixed
     */
    public function get($property)
    {
        // found? ok, return back!
        if (isset($this[$property])) {
            return $this[$property];
        } else {
            // if not found, try the prefixed version resp. the full URI
            if (false !== strpos($property, 'http://')) { // full URI
                $shortendProperty = $this->commonNamespaces->shortenUri($property);
                if (isset($this[$shortendProperty])) {
                    $this[$property] = $this[$shortendProperty];
                    return $this[$shortendProperty];
                }
            } else { // shorted version
                $extendedProperty = $this->commonNamespaces->extendUri($property);
                if (isset($this[$extendedProperty])) {
                    $this[$property] = $this[$extendedProperty];
                    return $this[$extendedProperty];
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

        foreach ($this as $key => $value) {
            if (is_array($value) || $value instanceof DataBlank) {
                $copy[$key] = $this->getArrayCopyRecursive($value);
            } elseif (is_string($key) && $key !== $value) {
                $copy[$key] = $value;
            } else {
                echo '

                '. $value;
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
     * Init instance by a simple array with properties as keys and according values.
     *
     * @param array $array
     * @todo implement a way to handle different $value data types
     */
    public function initByArray(array $array)
    {
        foreach ($array as $property => $value) {
            // predicate
            if ($this->options['use_prefixed_predicates']) {
                $property = $this->commonNamespaces->shortenUri($property);
            } else {
                $property = $this->commonNamespaces->extendUri($property);
            }

            // object
            if ($this->options['use_prefixed_objects']) {
                $value = $this->commonNamespaces->shortenUri($value);
            } else {
                $value = $this->commonNamespaces->extendUri($value);
            }

            $this->setValue($property, $value);
        }
    }

    /**
     * Init instance by a given SetResult instance. It only set properties and values of the
     * given subject. If for instance an object has also more properties, they will be ignored.
     * For such cases, use initByStoreSearch.
     *
     * @param SetResult $result
     * @param string $subjectUri
     * @param string $subject Default: s (optional)
     * @param string $predicate Default: p (optional)
     * @param string $object Default: o (optional)
     */
    public function initBySetResult(
        SetResult $result,
        string $subjectUri,
        string $subject = 's',
        string $predicate = 'p',
        string $object = 'o'
    ) {
        $subjectUri = $this->commonNamespaces->extendUri($subjectUri);

        foreach ($result as $entry) {
            // ignore entry if its subject is set but is not relevant
            if (isset($entry[$subject])) {
                $s = $entry[$subject];
                $subjectValue = $s->isNamed() ? $s->getUri() : $s->getBlankId();
                if ($subjectValue !== $subjectUri) {
                    continue;
                }
            }

            $p = $entry[$predicate];
            $o = $entry[$object];

            /*
             * predicate value
             */
            $predicateValue = $this->options['use_prefixed_predicates']
                ? $this->commonNamespaces->shortenUri($p->getUri())
                : $this->commonNamespaces->extendUri($p->getUri());

            /*
             * object value
             */
            if ($o->isNamed()) {
                $value = $this->options['use_prefixed_objects']
                    ? $this->commonNamespaces->shortenUri($o->getUri())
                    : $this->commonNamespaces->extendUri($o->getUri());
            } else {
                $value = $this->getNodeValue($o);
            }

            // set property key and object value
            $this->setValue($predicateValue, $value);
        }

        $result->rewind();

        if ($this->options['add_internal_data_fields']) {
            $this['_idUri'] = $subjectUri;
        }
    }

    /**
     * Init instance by a given StatementIterator instance.
     *
     * @param Saft\Rdf\StatementIterator $iterator
     * @param string $resourceUri URI of the resource to use
     * @todo support blank nodes
     */
    public function initByStatementIterator(StatementIterator $iterator, $resourceUri)
    {
        $entries = array();
        foreach ($iterator as $statement) {
            if ($statement->getSubject()->getUri() == $resourceUri) {
                $entries[] = array(
                    'p' => $statement->getPredicate(),
                    'o' => $statement->getObject()
                );
            }
        }

        return $this->initBySetResult(new SetResultImpl($entries), $resourceUri);
    }

    /**
     * @param Store $store
     * @param NamedNode $graph
     * @param string $resourceId
     */
    public function initByStoreSearch(Store $store, NamedNode $graph, string $resourceId)
    {
        if ($this->rdfHelpers->simpleCheckURI($resourceId)) {
            $resourceIdSubject = '<'. $resourceId .'>';
        } elseif ($this->rdfHelpers->simpleCheckBlankNodeId($resourceId)) {
            // leave it as it is
            $resourceIdSubject = $resourceId;
        } else {
            throw new \KnorkeException('Invalid $resourceId given (must be URI or blank node): '. $resourceId);
        }

        if ($this->options['add_internal_data_fields']) {
            $this->setValue('_idUri', $resourceId);
        }

        // ask store for all properties and values for the given resource
        $setResult = $store->query('SELECT * FROM <'. $graph->getUri() .'> WHERE {
            '. $resourceIdSubject .' ?p ?o.
        }');

        // init direct connections of the subject
        $this->initBySetResult($setResult, $resourceId);

        /*
         * recursive initiation of already stored objects, which are URIs or blank nodes
         */
        foreach ($this as $key => $value) {
            // to avoid infinite recursion
            if ($value == $resourceId) {
                continue;
            }

            // value is an array
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    if ($this->rdfHelpers->simpleCheckURI($subValue) || $this->rdfHelpers->simpleCheckBlankNodeId($subValue)) {
                        $valueDataBlank = new DataBlank($this->commonNamespaces, $this->rdfHelpers, $this->options);
                        $valueDataBlank->initByStoreSearch($store, $graph, $subValue);
                        if (1 < count($valueDataBlank)) {
                            $this[$key][$subKey] = $valueDataBlank;
                        }
                    }
                }

            // value is resource (try to load further information)
            } elseif ($this->rdfHelpers->simpleCheckURI($value) || $this->rdfHelpers->simpleCheckBlankNodeId($value)) {
                $valueDataBlank = new DataBlank($this->commonNamespaces, $this->rdfHelpers, $this->options);
                $valueDataBlank->initByStoreSearch($store, $graph, $value);
                if (1 < count($valueDataBlank)) {
                    $this[$key] = $valueDataBlank;
                }
                // if nothing is in $valueDataBlank, the current value remains untouched
            }
        }
    }

    /**
     * Helps setting values, but checking if key is already in use. If so, change value to array so that multiple
     * values for the same key can be stored.
     */
    protected function setValue($key, $value)
    {
        // value already set, but is not the same we already stored
        if (isset($this[$key]) && $this[$key] !== $value) {
            // is already an array, add further item
            if (is_array($this[$key])) {
                // $this[$key][$value] = $value;
                $this[$key][] = $value;
            // is a string, make it to array
            } else {
                // $this[$key] = array($this[$key] => $this[$key], $value => $value);
                $this[$key] = array(0 => $this[$key], 1 => $value);
            }
        // value not set already
        } else {
            $this[$key] = $value;
        }
    }
}
