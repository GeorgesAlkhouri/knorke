<?php

namespace Knorke;

use Knorke\Data\ParserFactory;
use Saft\Rdf\NamedNode;
use Saft\Rdf\NodeUtils;
use Saft\Store\Store;

/**
 *
 */
class Importer
{
    /**
     * @var NodeUtils
     */
    protected $rdfHelpers;

    /**
     * @var array of Parser
     */
    protected $parsers;

    /**
     * @var ParserFactory
     */
    protected $parserFactory;

    /**
     * @var Store
     */
    protected $store;

    /**
     * @param Store $store
     * @param ParserFactory $parserFactory
     * @param RdfHelpers $rdfHelpers
     */
    public function __construct(Store $store, ParserFactory $parserFactory, RdfHelpers $rdfHelpers)
    {
        $this->store = $store;

        $this->parsers = array();

        $this->rdfHelpers = $rdfHelpers;

        $this->parserFactory = $parserFactory;
    }

    /**
     * @param string|resource $file
     * @return string
     * @throws \Exception if parameter $file is not of type string or resource.
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
            throw new \Exception('Parameter $file must be the string itself or a filename.');
        }
    }

    /**
     * @param string $filepath
     * @param NamedNode $graph Graph to import data into.
     * @param true
     * @throws \Exception if parameter $file is not of type string or resource.
     * @throws \Exception if a non-n-triples file is to import.
     */
    public function importFile($filename, NamedNode $graph)
    {
        $content = file_get_contents($filename);
        $serialization = $this->getSerialization($content);
        if (null == $serialization) {
            throw new \Exception('Your file/string has an unknown serialization: '. $serialization);
        }

        return $this->importString($content, $graph, $serialization);
    }

    /**
     * Imports a string assuming its serialized as n-triples.
     *
     * @param string $string
     * @param NamedNode $graph
     * @param string $serialization
     * @param true
     * @throws \Exception if parameter $graph is null.
     */
    public function importString($string, $graph, $serialization = 'n-triples')
    {
        if (in_array($serialization, $this->parserFactory->getSupportedSerializations())) {
            if (false == isset($this->parsers[$serialization])) {
                $this->parsers[$serialization] = $this->parserFactory->createParserFor($serialization);
            }
        } else {
            throw new \Exception('Given serialization is unknown: '. $serialization);
        }

        // parse string
        $iterator = $this->parsers[$serialization]->parseStringToIterator($string);

        // import its statements into the store
        $this->store->addStatements($iterator, $graph);

        return true;
    }
}
