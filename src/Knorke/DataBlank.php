<?php

namespace Knorke;

use Knorke\Exception\KnorkeException;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\NamedNode;
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
     * Helper function to allow content gathering by using full URIs or prefixed ones. If the indirect way worked, the
     * previously try $property will be applied to.
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
     * Init instance by a simple array with properties as keys and according values.
     *
     * @param array $array
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
     * Init instance by a given SetResult instance.
     *
     * @param SetResult $result
     * @param string $subjectUri
     * @param string $predicate Default: p (optional)
     * @param string $object Default: o (optional)
     * @todo support blank nodes
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
            // ignore entry if its subject is not relevant
            if (isset($entry[$subject])) {
                $subjectValue = $entry[$subject]->isNamed()
                    ? $entry[$subject]->getUri()
                    : $entry[$subject]->getBlankId();
                if ($subjectValue !== $subjectUri) {
                    continue;
                }
            }

            $predicateValue = $entry[$predicate]->getUri();

            // named node
            if ($entry[$object]->isNamed()) {
                $value = $entry[$object]->getUri();
            // literal
            } elseif ($entry[$object]->isLiteral()) {
                $value = $entry[$object]->getValue();
            // blank node
            } elseif ($entry[$object]->isBlank()) {
                $value = '_:' . $entry[$object]->getBlankId();
            }

            // prefix predicates if wanted
            if ($this->options['use_prefixed_predicates']) {
                $predicateValue = $this->transformPrefixToFullVersionOrBack($entry[$predicate]->getUri());

            // or transform all predicates back to their full length, if possible
            } else {
                $predicateValue = $this->transformPrefixToFullVersionOrBack($entry[$predicate]->getUri(), 'not_prefixed');
            }

            if ($entry[$object]->isNamed()) {
                // prefix objects if wanted
                if ($this->options['use_prefixed_objects']) {
                    $value = $this->transformPrefixToFullVersionOrBack($entry[$object]->getUri());
                // or transform all objects back to their full length, if possible
                } else {
                    $value = $this->transformPrefixToFullVersionOrBack($entry[$object]->getUri(), 'not_prefixed');
                }
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
            if ($statement->getSubject() == $resourceUri) {
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

        // recursive init objects which are URIs and connections too
        foreach ($this as $key => $value) {
            // value is URI or blank node
            if ($this->rdfHelpers->simpleCheckURI($value)
                || $this->rdfHelpers->simpleCheckBlankNodeId($value)) {

                $valueDataBlank = new DataBlank($this->commonNamespaces, $this->rdfHelpers);
                // init value instance recursively
                if ($value !== $resourceId) {
                    $valueDataBlank->initByStoreSearch($store, $graph, $value);
                    if (1 < count($valueDataBlank)) {
                        $this[$key] = $valueDataBlank;
                    }
                }
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
                $this[$key][] = $value;
            // is a string, make it to array
            } else {
                $this[$key] = array($this[$key], $value);
            }
        // value not set already
        } else {
            $this[$key] = $value;
        }
    }

    /**
     * Transforms a given URI to a prefixed version or back.
     *
     * @param string $uri
     * @param string $mode possible values: prefixed, not_prefixed. default: prefixed
     * @return string
     */
    protected function transformPrefixToFullVersionOrBack($uri, $mode = 'prefixed')
    {
        if ('prefixed' == $mode) {
            foreach ($this->commonNamespaces->getNamespaces() as $ns => $nsUri) {
                if (false !== strpos($uri, $nsUri)) {
                    return str_replace($nsUri, $ns .':', $uri);
                }
            }

        // replace prefix with full URI if possible
        } else { // = not_prefixed
            foreach ($this->commonNamespaces->getNamespaces() as $ns => $nsUri) {
                if (false !== strpos($uri, $ns .':')) {
                    return str_replace($ns .':', $nsUri, $uri);
                }
            }
        }

        return $uri;
    }
}
