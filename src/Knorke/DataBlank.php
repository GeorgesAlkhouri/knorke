<?php

namespace Knorke;

use Saft\Rdf\StatementIterator;
use Saft\Sparql\Result\SetResult;

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
     * @param SetResult $result
     * @param string $subjectUri
     * @param string $predicate Default: p (optional)
     * @param string $object Default: o (optional)
     * @todo support blank nodes
     */
    public function initBySetResult(SetResult $result, $subjectUri, $predicate = 'p', $object = 'o')
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
