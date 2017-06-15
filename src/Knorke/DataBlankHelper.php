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
    protected $graphs;
    protected $nodeFactory;
    protected $options;
    protected $statementFactory;
    protected $store;

    public function __construct(
        CommonNamespaces $commonNamespaces,
        StatementFactory $statementFactory,
        NodeFactory $nodeFactory,
        RdfHelpers $rdfHelpers,
        Store $store,
        array $graphs,
        array $options = array()
    ) {
        $this->commonNamespaces = $commonNamespaces;
        $this->graphs = $graphs;
        $this->nodeFactory = $nodeFactory;
        $this->options = array_merge(array(
            'max_depth' => 2
        ), $options);
        $this->rdfHelpers = $rdfHelpers;
        $this->statementFactory = $statementFactory;
        $this->store = $store;
    }

    /**
     * @param array $graphs Array of NamedNode instances
     */
    protected function buildGraphsList(array $graphs) : string
    {
        $fromGraphList = array();

        foreach ($graphs as $graph) {
            $fromGraphList[] = 'FROM <'. $graph->getUri(). '>';
        }

        return implode(' ', $fromGraphList);
    }

    /**
     * @param array $options
     * @return DataBlank Fresh DataBlank instance.
     */
    public function createDataBlank(array $options = array()) : DataBlank
    {
        return new DataBlank($this->commonNamespaces, $this->rdfHelpers, $options);
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

        $graphs = $this->buildGraphsList($this->graphs);

        $result = $this->store->query('SELECT * '. $graphs .' WHERE {
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

            $blanks[$resourceId] = $this->createDataBlank();
            $blanks[$resourceId]->initByStoreSearch(
                $this->store,
                $this->graphs,
                $resourceId,
                $this->options['max_depth']
            );
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
     * @param string $resourceUri
     * @return DataBlank
     */
    public function load(string $resourceUri) : DataBlank
    {
        $dataBlank = $this->createDataBlank();
        $dataBlank->initByStoreSearch($this->store, $this->graphs, $resourceUri, $this->options['max_depth']);

        return $dataBlank;
    }
}
