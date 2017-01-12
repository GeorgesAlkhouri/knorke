<?php

namespace Knorke;

use Saft\Rdf\StatementIterator;

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
 *      $blank = new Saft\Rapid\Blank($setResult, 'p', 'o');
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
     * @param array $options optional, default=array()
     */
    public function __construct(CommonNamespaces $commonNamespaces, array $options = array())
    {
        $this->commonNamespaces = $commonNamespaces;
        $this->options = array_merge(
            array(
                'use_prefixed_predicates' => true,
                'use_prefixed_objects' => true
            ),
            $options
        );
    }

    /**
     * Init instance by a given SetResult instance.
     *
     * @param Saft\Sparql\Result\SetResult $result
     * @param string $subjectUri
     * @param string $predicate Default: p (optional)
     * @param string $object Default: o (optional)
     * @todo support blank nodes
     */
    public function initBySetResult(\Saft\Sparql\Result\SetResult $result, $subjectUri, $predicate = 'p', $object = 'o')
    {
        foreach ($result as $entry) {
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

            // furthermore, set namespace shortcut (e.g. rdf) as key and object value, to improve
            // handling later on.
            if ($this->options['use_prefixed_predicates']) {
                foreach ($this->commonNamespaces->getNamespaces() as $ns => $nsUri) {
                    if (false !== strpos($entry[$predicate]->getUri(), $nsUri)) {
                        $predicateValue = str_replace($nsUri, $ns .':', $entry[$predicate]->getUri());
                        break;
                    }
                }
            }
            if ($this->options['use_prefixed_objects']) {
                foreach ($this->commonNamespaces->getNamespaces() as $ns => $nsUri) {
                    $objectObj = $entry[$object];
                    if ($objectObj->isNamed() && false !== strpos($objectObj->getUri(), $nsUri)) {
                        $objectUri = $objectObj->getUri();
                        $value = str_replace($nsUri, $ns .':', $objectUri);
                    }
                }
            }

            // set property key and object value
            $this->setValue($predicateValue, $value);
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
        $this['__subjectUri'] = $this->commonNamespaces->shortenUri($resourceUri);

        // go through given statements
        foreach ($iterator as $statement) {
            // if the current statement has as subject URI the same as the given $resourceUri, integrate
            // its property + value into this instance.
            if ($statement->getSubject()->getUri() == $resourceUri) {
                if ($statement->getObject()->isNamed()) {
                    $value = $statement->getObject()->getUri();
                } elseif ($statement->getObject()->isLiteral()) {
                    $value = $statement->getObject()->getValue();
                }

                // furthermore, set namespace shortcut (e.g. rdf) as key and object value, to improve
                // handling later on. remove original entries with long predicate URIs.
                $shorterProperty = null;
                $shorterObject = null;
                foreach ($this->commonNamespaces->getNamespaces() as $ns => $nsUri) {
                    // shorten property
                    if ($this->options['use_prefixed_predicates']
                        && false !== strpos($statement->getPredicate()->getUri(), $nsUri)) {
                        $shorterProperty = str_replace($nsUri, $ns .':', $statement->getPredicate()->getUri());
                    }
                    // shorten object
                    if ($this->options['use_prefixed_objects']
                        && $statement->getObject()->isNamed()
                        && false !== strpos($statement->getObject()->getUri(), $nsUri)) {
                        $shorterObject = str_replace($nsUri, $ns .':', $statement->getObject()->getUri());
                    }
                }

                // store shorten values
                if (null !== $shorterProperty && null !== $shorterObject) {
                    $this->setValue($shorterProperty, $shorterObject);

                } elseif (null !== $shorterProperty) {
                    $this->setValue($shorterProperty, $value);

                } elseif (null !== $shorterObject) {
                    $this->setValue($statement->getPredicate()->getUri(), $shorterObject);

                // store full length values
                } else {
                    $this->setValue($statement->getPredicate()->getUri(), $value);
                }

                // TODO blank nodes
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
}
