<?php

namespace Knorke\Data;

use Saft\Addition\hardf\Data\ParserHardf;
use Saft\Data\NQuadsParser;
use Saft\Data\RDFXMLParser;
use Saft\Data\ParserFactory as ParserFactoryInterface;
use Saft\Rdf\NodeFactory;
use Saft\Rdf\RdfHelpers;
use Saft\Rdf\StatementFactory;
use Saft\Rdf\StatementIteratorFactory;

/**
 * This factory creates the most suitable parser instance for a given serialization.
 */
class ParserFactory implements ParserFactoryInterface
{
    /**
     * @var NodeFactory
     */
    protected $nodeFactory;

    /**
     * @var RdfHelpers
     */
    protected $rdfHelpers;

    /**
     * @var StatementFactory
     */
    protected $statementFactory;

    /**
     * @var StatementIteratorFactory
     */
    protected $statementIteratorFactory;

    /**
     * @param NodeFactory $nodeFactory
     * @param StatementFactory $statementFactory
     * @param RdfHelpers $rdfHelpers
     */
    public function __construct(
        NodeFactory $nodeFactory,
        StatementFactory $statementFactory,
        StatementIteratorFactory $statementIteratorFactory,
        RdfHelpers $rdfHelpers
    ) {
        $this->nodeFactory = $nodeFactory;
        $this->rdfHelpers = $rdfHelpers;
        $this->statementFactory = $statementFactory;
        $this->statementIteratorFactory = $statementIteratorFactory;
    }

    /**
     * @param string $serialization
     * @return null|Parser
     */
    public function createParserFor($serialization)
    {
        // try first our own parsers
        if ('rdf-xml' == $serialization) {
            return new RDFXMLParser(
                $this->nodeFactory,
                $this->statementFactory,
                $this->statementIteratorFactory,
                $this->rdfHelpers
            );
        } elseif ('n-triples' == $serialization || 'n-quads' == $serialization) {
            return new NQuadsParser(
                $this->nodeFactory,
                $this->statementFactory,
                $this->statementIteratorFactory,
                $this->rdfHelpers
            );

        } elseif ('turtle' == $serialization) {
            return new ParserHardf(
                $this->nodeFactory,
                $this->statementFactory,
                $this->statementIteratorFactory,
                $this->rdfHelpers,
                $serialization
            );
        }

        return null;
    }

    /**
     * Returns supported serializations of all used parsers.
     *
     * @return array
     */
    public function getSupportedSerializations() : array
    {
        return array('n-triples', 'n-quads', 'rdf-xml', 'turtle');
    }
}
