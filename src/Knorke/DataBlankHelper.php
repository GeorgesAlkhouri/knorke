<?php

namespace Knorke;

use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\NamedNode;
use Saft\Rdf\Node;
use Saft\Rdf\NodeFactory;
use Saft\Rdf\RdfHelpers;
use Saft\Rdf\StatementFactory;
use Saft\Store\Store;

class DataBlankHelper
{
    protected $commonNamespaces;
    protected $graph;
    protected $nodeFactory;
    protected $statementFactory;
    protected $store;

    public function __construct(
        CommonNamespaces $commonNamespaces,
        StatementFactory $statementFactory,
        NodeFactory $nodeFactory,
        RdfHelpers $rdfHelpers,
        Store $store,
        NamedNode $graph
    ) {
        $this->commonNamespaces = $commonNamespaces;
        $this->graph = $graph;
        $this->nodeFactory = $nodeFactory;
        $this->rdfHelpers = $rdfHelpers;
        $this->statementFactory = $statementFactory;
        $this->store = $store;
    }

    /**
     * @param string $typeUri
     * @param string $hash Optional, default: null
     * @param string $baseUri Optional, default: null. Will be used as prefix for auto generated triples.
     * @return DataBlank
     */
    public function dispense(string $typeUri, string $hash = null, string $baseUri = null) : DataBlank
    {
        $blank = new DataBlank($this->commonNamespaces, $this->rdfHelpers);

        // set type
        $blank['rdf:type'] = $typeUri;

        // set base URI to typeUri if not provided
        if (null === $baseUri) {
            $baseUri = $typeUri .'/';
        }

        // if typeUri doesnt contain :// but is structured like foaf:Person
        // add it to base URI
        if (false === strpos($typeUri, '://')) {
            $baseUri .= strtolower(str_replace(':', '-', $typeUri) .'/');
        }

        // if valid hash was provided
        if (null !== $hash && 0 < strlen($hash)) {
            $blank['_idUri'] = $baseUri .'id/'. $hash;

        // if no valid hash was provided, generated one automatically
        } else {
            $blank['_idUri'] = $baseUri .'id/'. substr(hash('sha256', rand(0, 1000) . time()), 0, 8);
        }

        return $blank;
    }

    /**
     * @param Node $node
     * @throws \Exception on unknown Node type
     */
    protected function getNodeValue(Node $node)
    {
        if ($node->isConcrete()) {
            // uri
            if ($node->isNamed()) {
                $value = $node->getUri();
            // literal
            } elseif ($node->isLiteral()) {
                $value = $node->getValue();
            // blanknode
            } elseif ($node->isBlank()) {
                $value = $node->toNQuads();
            } else {
                throw new \Exception('Unknown Node type given');
            }

        } else { // anypattern
            $value = (string)$node;
        }

        return $value;
    }

    /**
     * @param string $typeUri
     * @param string $wherePart Optional, default: ''
     * @return array
     */
    public function find(string $typeUri) : array
    {
        $result = $this->store->query('SELECT * FROM <'. $this->graph->getUri() .'> WHERE {
            ?s <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <'. $typeUri .'>.
        }');

        $blanks = array();
        foreach ($result as $key => $entry) {
            if ($entry['s'] instanceof Node) {
                $resourceId = $this->getNodeValue($entry['s']);
            } else {
                $resourceId = $entry['s'];
            }

            $blanks[$key] = new DataBlank($this->commonNamespaces, $this->rdfHelpers);
            $blanks[$key]->initByStoreSearch($this->store, $this->graph, $resourceId);
        }

        return $blanks;
    }

    /**
     * @param string $typeUri
     * @param string $baseUri
     * @param string $hash
     * @return DataBlank
     */
    public function load(string $resourceUri) : DataBlank
    {
        $result = $this->store->query('SELECT * FROM <'. $this->graph->getUri() .'> WHERE {
            <'. $resourceUri .'> ?p ?o.
        }');

        $dataBlank = new DataBlank($this->commonNamespaces, $this->rdfHelpers);
        $dataBlank->initBySetResult($result, $resourceUri);

        $dataBlank['_idUri'] = $resourceUri;

        return $dataBlank;
    }

    /**
     * @param DataBlank $blank
     * @return string Hash/id of the given
     */
    public function store(DataBlank $blank)
    {
        if (isset($blank['_idUri'])) {
            $statements = array();
            $resourceUri = $blank['_idUri'];

            // clone $blank to unset entries without affecting given one
            $blankCopy = clone $blank;
            unset($blankCopy['_idUri']);

            foreach (array_keys($blankCopy->getArrayCopy()) as $key) {

                if (true === $this->rdfHelpers->simpleCheckURI($blankCopy[$key])) {
                    $object = $this->nodeFactory->createNamedNode($blankCopy[$key]);
                } else {
                    $object = $this->nodeFactory->createLiteral($blankCopy[$key]);
                }

                $statements[] = $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode($resourceUri),
                    $this->nodeFactory->createNamedNode($key),
                    $object,
                    $this->graph
                );
            }

            $this->store->addStatements($statements);

            // TODO support datablank as object
        } else {
            throw new \Exception('Property _idUri not set, therefore not storeable.');
        }
    }
}
