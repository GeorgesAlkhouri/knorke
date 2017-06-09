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
     */
    protected function getNodeForGivenValue(string $value) : Node
    {
        // named node
        if ($this->rdfHelpers->simpleCheckUri($value)) {
            return $this->nodeFactory->createNamedNode($this->commonNamespaces->extendUri($value));

        // blank node
        } elseif ($this->rdfHelpers->simpleCheckBlankNodeId($value)) {
            return $this->nodeFactory->createBlankNode(substr($value, 2));

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
     * @param NodeNamed|BlankNode $startResource
     * @param array $array
     * @return array
     */
    public function transformPhpArrayToStatementArray(Node $startResource, array $array) : array
    {
        if (0 == count($array)) {
            throw new KnorkeException('Parameter $array is empty.');
        }

        if (false == $startResource->isNamed() && false == $startResource->isBlank()) {
            throw new KnorkeException('Parameter $startResource needs to be of type NamedNode or BlankNode.');
        }

        $result = array();

        foreach ($array as $uri => $value) {
            if ($this->rdfHelpers->simpleCheckUri($uri)) {
                $extendedUri = $this->commonNamespaces->extendUri($uri);
            } else {
                throw new KnorkeException('Array key needs to be an URI: '. $uri);
            }

            if (is_array($value)) {
                // assume array with key-value pairs
                if (is_string(array_keys($value)[0])) {
                    // $referenceNode = $this->nodeFactory->createBlankNode(hash('sha256', time() . rand(0, 1000)));
                    $referenceNode = $this->nodeFactory->createNamedNode('bn://' . hash('sha256', microtime() . rand(0, 1000)));

                    $result[] = $this->statementFactory->createStatement(
                        $startResource,
                        $this->nodeFactory->createNamedNode($extendedUri),
                        $referenceNode
                    );

                    $result = array_merge($this->transformPhpArrayToStatementArray($referenceNode, $value), $result);

                // assume array of array
                } else {
                    foreach ($value as $entry) {
                        // $referenceNode = $this->nodeFactory->createBlankNode(hash('sha256', time() . rand(0, 1000)));
                        $referenceNode = $this->nodeFactory->createNamedNode('bn://' . hash('sha256', microtime() . rand(0, 1000)));

                        $result[] = $this->statementFactory->createStatement(
                            $startResource,
                            $this->nodeFactory->createNamedNode($extendedUri),
                            $referenceNode
                        );

                        $result = array_merge($this->transformPhpArrayToStatementArray($referenceNode, $entry), $result);
                    }
                }

            } else {
                $result[] = $this->statementFactory->createStatement(
                    $startResource,
                    $this->nodeFactory->createNamedNode($extendedUri),
                    $this->getNodeForGivenValue($value)
                );
            }
        }

        return $result;
    }
}
