<?php

namespace Knorke;

use Knorke\Data\ParserFactory;
use Knorke\Exception\KnorkeException;
use Saft\Rdf\BlankNode;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\Node;
use Saft\Rdf\NamedNode;
use Saft\Rdf\NodeFactory;
use Saft\Rdf\RdfHelpers;
use Saft\Rdf\StatementFactory;
use Saft\Store\Store;

/**
 *
 */
class Importer
{
    /**
     * @var CommonNamespaces
     */
    protected $commonNamespaces;

    /**
     * @var NodeFactory
     */
    protected $nodeFactory;

    /**
     * @var array of Parser instances
     */
    protected $parsers;

    /**
     * @var ParserFactory
     */
    protected $parserFactory;

    /**
     * @var RdfHelpers
     */
    protected $rdfHelpers;

    /**
     * @var StatementFactory
     */
    protected $statementFactory;

    /**
     * @var Store
     */
    protected $store;

    /**
     * @param Store $store
     * @param ParserFactory $parserFactory
     * @param NodeFacotry $nodeFactory
     * @param StatementFactory $statementFactory
     * @param RdfHelpers $rdfHelpers
     * @param CommonNamespaces $commonNamespaces
     */
    public function __construct(
        Store $store,
        ParserFactory $parserFactory,
        NodeFactory $nodeFactory,
        StatementFactory $statementFactory,
        RdfHelpers $rdfHelpers,
        CommonNamespaces $commonNamespaces
    ) {
        $this->commonNamespaces = $commonNamespaces;

        $this->store = $store;

        $this->nodeFactory = $nodeFactory;

        $this->parsers = array();

        $this->parserFactory = $parserFactory;

        $this->rdfHelpers = $rdfHelpers;

        $this->statementFactory = $statementFactory;
    }

    /**
     * @param string $value
     * @return Node
     * @throws \Exception if blank node was given.
     */
    protected function getNodeForGivenValue(string $value) : Node
    {
        // named node
        if ($this->rdfHelpers->simpleCheckUri($value)) {
            return $this->nodeFactory->createNamedNode($this->commonNamespaces->extendUri($value));

        // blank node
        } elseif ($this->rdfHelpers->simpleCheckBlankNodeId($value)) {
            throw new \Exception('Blank nodes are not supported anymore: ' . $value);

        // literal
        } else {
            return $this->nodeFactory->createLiteral($value);
        }
    }

    /**
     * @param string|resource $file
     * @return string
     * @throws KnorkeException if parameter $file is not of type string or resource.
     */
    public function getSerialization($target)
    {
        $format = null;
        $short = null;

        // filename given
        if (is_string($target) && file_exists($target)) {
            $target = file_get_contents($target);
        }

        // string given
        if (is_string($target)) {
            $short = $target;
            return $this->rdfHelpers->guessFormat($short);
        } else {
            throw new KnorkeException('Parameter $file must be the string itself or a filename.');
        }
    }

    /**
     * @param string $filepath
     * @param NamedNode $graph Graph to import data into.
     * @param true
     * @throws KnorkeException if parameter $file is not of type string or resource.
     * @throws KnorkeException if a non-n-triples file is to import.
     */
    public function importFile($filename, NamedNode $graph)
    {
        $content = file_get_contents($filename);
        $serialization = $this->getSerialization($content);
        if (null == $serialization) {
            throw new KnorkeException('Your file/string has an unknown serialization: '. $serialization);
        }

        return $this->importString($content, $graph, $serialization);
    }

    /**
     * @param array $array
     * @param NodeNamed|BlankNode $startResource
     * @param NamedNode $graph
     */
    public function importDataValidationArray(array $array, Node $startResource, NamedNode $graph)
    {
        // transforms array of array structures to valid statements
        $statements = $this->transformPhpArrayToStatementArray($startResource, $array);

        // add statements to store
        $this->store->addStatements($statements, $graph);
    }

    /**
     * Imports a string assuming its serialized as n-triples.
     *
     * @param string $string
     * @param NamedNode $graph
     * @param string $serialization Default is n-triples
     * @return true If everything worked well.
     * @throws KnorkeException if parameter $graph is null.
     */
    public function importString($string, NamedNode $graph, string $serialization = 'n-triples')
    {
        if (in_array($serialization, $this->parserFactory->getSupportedSerializations())) {
            if (false == isset($this->parsers[$serialization])) {
                $this->parsers[$serialization] = $this->parserFactory->createParserFor($serialization);
            }
        } else {
            throw new KnorkeException('Given serialization is unknown: '. $serialization);
        }

        // parse string
        $iterator = $this->parsers[$serialization]->parseStringToIterator($string);

        // import its statements into the store
        $this->store->addStatements($iterator, $graph);

        return true;
    }

    /**
     * @param array $array
     */
    protected function isStringList(array $array) : bool
    {
        foreach ($array as $value) {
            if (false === is_string($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns true, if given $array contains at least 1 resource array.
     *
     * @param array $array
     */
    protected function isMixedListOfStringsOrResourceArrays(array $array) : bool
    {
        foreach ($array as $value) {
            if (is_string($value) ) {
                // OK
            } elseif (isset($value['_idUri'])) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    /**
     * @param NodeNamed|BlankNode $startResource
     * @param array $array
     * @return array
     */
    public function transformPhpArrayToStatementArray(Node $startResource, array $array) : array
    {
        if (false == $startResource->isNamed() && false == $startResource->isBlank()) {
            throw new KnorkeException('Parameter $startResource needs to be of type NamedNode or BlankNode.');
        }

        $result = array();

        /*
            example $array given as parameter:

            array(
                'http://1/' => 'http://1/2',
                'http://2/' => array(
                    0 => 'http://2/1',
                    'http://2/2',
                ),
                'http://3/' => array(
                    // resource 1
                    'http://3/1',

                    // resource in list with properties and objects
                    array(
                        '_idUri' => 'http://3/2',
                        'http://3/3' => array (
                            // should force function to use this URI instead of a generated one
                            '_idUri' => 'http://3/4',
                            'http://3/5' => 'http://3/6'
                        )
                    )
                ),
                'http://4/' => array(
                    '_idUri' => 'http://4/1',
                    'http://4/2' => 'http://4/3',
                ),
            )
         */
        foreach ($array as $propertyOfStartResource => $value) {
            /*
                $array = array(
                    'http://foobar/' => ...
                )
             */
            if ($this->rdfHelpers->simpleCheckUri($propertyOfStartResource)) {
                /*
                    array(
                        'http://1/' => 'http://1/2',
                 */
                if (is_string($value)) {
                    $result[] = $this->statementFactory->createStatement(
                        $startResource,
                        $this->nodeFactory->createNamedNode($propertyOfStartResource),
                        $this->getNodeForGivenValue($value)
                    );

                /*
                    $value = array(
                        '_idUri' => 'http://id/uri/',
                        ...
                    )
                 */
                } elseif (isset($value['_idUri'])) {
                    // connect startResource with sub resource
                    $result[] = $this->statementFactory->createStatement(
                        $startResource,
                        $this->nodeFactory->createNamedNode($propertyOfStartResource),
                        $this->getNodeForGivenValue($value['_idUri'])
                    );

                    $valueCopy = $value;
                    unset($valueCopy['_idUri']);

                    $result = array_merge(
                        $this->transformPhpArrayToStatementArray(
                            $this->nodeFactory->createNamedNode($value['_idUri']),
                            $valueCopy
                        ),
                        $result
                    );

                /*
                    'http://2/' => array(
                        0 => 'http://2/1',
                        'http://2/2',
                    ),
                 */
                } elseif (is_array($value) && $this->isStringList($value)) {
                    foreach ($value as $string) {
                        $result[] = $this->statementFactory->createStatement(
                            $startResource,
                            $this->nodeFactory->createNamedNode($propertyOfStartResource),
                            $this->getNodeForGivenValue($string)
                        );
                    }

                /*
                    'http://3/' => array(
                        'http://3/1',
                        array(
                            '_idUri' => 'http://3/2',
                            ...
                        )
                    ),
                 */
                } elseif (is_array($value) && $this->isMixedListOfStringsOrResourceArrays($value)) {
                    foreach ($value as $entry) {
                        // e.g. 'http://3/1',
                        if (is_string($entry)) {
                            $result[] = $this->statementFactory->createStatement(
                                $startResource,
                                $this->nodeFactory->createNamedNode($propertyOfStartResource),
                                $this->getNodeForGivenValue($entry)
                            );

                        /*
                            $entry = array(
                                '_idUri' => 'http://3/2',
                                ...
                         */
                        } else {
                            // create connection between start resource and sub resource (via _idUri)
                            $result[] = $this->statementFactory->createStatement(
                                $startResource,
                                $this->nodeFactory->createNamedNode($propertyOfStartResource),
                                $this->nodeFactory->createNamedNode($entry['_idUri'])
                            );

                            // recursive call of transformPhpArrayToStatementArray
                            $entryCopyWithoutIdUri = $entry;
                            unset($entryCopyWithoutIdUri['_idUri']);
                            $result = array_merge(
                                $this->transformPhpArrayToStatementArray(
                                    $this->nodeFactory->createNamedNode($entry['_idUri']),
                                    $entryCopyWithoutIdUri
                                ),
                                $result
                            );
                        }
                    }
                }

            /*
                array(
                    ...
                    array(
                        '_idUri' => 'http://9',
                        'http://10' => 'http://11'
                    )
                )
             */
            } elseif (is_array($value) && isset($value['_idUri'])) {
                throw new KnorkeException('Missing property, because $key is not an URI: '. $propertyOfStartResource);
            }
        }

        return $result;
    }
}
