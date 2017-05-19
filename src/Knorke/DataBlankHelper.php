<?php

namespace Knorke;

use Knorke\Exception\KnorkeException;
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
     * @param array $options
     */
    public function createDataBlank(array $options = array())
    {
        return new DataBlank($this->commonNamespaces, $this->rdfHelpers, $options);
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
     * Finds resources (and all their properties+objects) for a given type URI. You can add
     * a where part to tighten your search field. Be aware of the used store engine, if it
     * supports certain queries.
     *
     * @param string $typeUri
     * @param string $wherePart Optional, default: ''
     * @return array
     */
    public function find(string $typeUri, string $wherePart = '') : array
    {
        $typeUri = $this->commonNamespaces->extendUri($typeUri);

        $result = $this->store->query('SELECT * FROM <'. $this->graph->getUri() .'> WHERE {
            ?s ?p ?o.
            ?s <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <'. $typeUri .'>.
            '. $wherePart .'
        }');

        $blanks = array();
        foreach ($result as $key => $entry) {
            if ($entry['s'] instanceof Node) {
                $resourceId = $this->commonNamespaces->extendUri($this->getNodeValue($entry['s']));
            } else {
                $resourceId = $this->commonNamespaces->extendUri($entry['s']->getUri());
            }

            $blanks[$resourceId] = new DataBlank($this->commonNamespaces, $this->rdfHelpers);
            $blanks[$resourceId]->initByStoreSearch($this->store, $this->graph, $resourceId);
        }

        return $blanks;
    }

    /**
     * Finds one resource (and all their properties+objects) for a given type URI, if available.
     * You can add a where part to tighten your search field. Be aware of the used store engine,
     * if it supports certain queries.
     *
     * @param string $typeUri
     * @param string $wherePart Optional, default: ''
     * @return DataBlank|null
     * @throws \KnorkeException if more than one resource was found.
     */
    public function findOne(string $typeUri, string $wherePart = '')
    {
        $result = $this->find($typeUri, $wherePart);

        // more than one entry found
        if (1 < count($result)) {
            throw new KnorkeException('More than one entry for the given typeUri and wherePart found.');

        // one entry found
        } elseif (1 == count($result)) {
            return array_values($result)[0];

        // nothing found
        } else {
            return null;
        }
    }

    /**
     * @param string $typeUri
     * @param string $baseUri
     * @param string $hash
     * @return DataBlank
     */
    public function load(string $resourceUri) : DataBlank
    {
        $dataBlank = $this->createDataBlank();
        $dataBlank->initByStoreSearch($this->store, $this->graph, $resourceUri);

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

                // if entry is a datablank too, recall store function for this entry
                if ($blankCopy[$key] instanceof DataBlank) {
                    $this->store($blankCopy[$key]);

                    // create relation between current datablank and referenced one
                    $statements[] = $this->statementFactory->createStatement(
                        $this->nodeFactory->createNamedNode($resourceUri),
                        $this->nodeFactory->createNamedNode($key),
                        $this->nodeFactory->createNamedNode($blankCopy[$key]['_idUri']),
                        $this->graph
                    );

                    continue;

                } elseif (true === $this->rdfHelpers->simpleCheckURI($blankCopy[$key])) {
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

    public function trash(DataBlank $blank)
    {
        $subjectUri = $blank['_idUri'];
        unset($blank['_idUri']);

        $subjectNode = $this->nodeFactory->createNamedNode($this->commonNamespaces->extendUri($subjectUri));

        foreach ($blank as $property => $value) {
            if ($value instanceof DataBlank) {
                $this->trash($value);
                continue;
            }

            $property = $this->commonNamespaces->extendUri($property);

            if ($this->rdfHelpers->simpleCheckURI($value)) {
                $valueNode = $this->nodeFactory->createNamedNode(
                    $this->commonNamespaces->extendUri($value)
                );
            } elseif ($this->rdfHelpers->simpleCheckBlankNodeId($value)) {
                $valueNode = $this->nodeFactory->createBlankNode($value);
            } else {
                $valueNode = $this->nodeFactory->createLiteral($value);
            }

            // remove statement
            $this->store->deleteMatchingStatements(
                $this->statementFactory->createStatement(
                    $subjectNode,
                    $this->nodeFactory->createNamedNode($property),
                    $valueNode,
                    $this->graph
                )
            );
        }
    }
}
