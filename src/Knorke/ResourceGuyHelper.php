<?php

namespace Knorke;

use Knorke\Exception\KnorkeException;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\Node;
use Saft\Rdf\NodeFactory;
use Saft\Rdf\RdfHelpers;
use Saft\Rdf\StatementFactory;
use Saft\Store\Store;

class ResourceGuyHelper
{
    protected $graphs;
    protected $rdfHelpers;
    protected $nodeFactory;
    protected $store;
    protected $statementFactory;

    public function __construct(
        Store $store,
        array $graphs,
        StatementFactory $statementFactory,
        NodeFactory $nodeFactory,
        RdfHelpers $rdfHelpers,
        CommonNamespaces $commonNamespaces
    ) {
        $this->commonNamespaces = $commonNamespaces;
        $this->nodeFactory = $nodeFactory;
        $this->rdfHelpers = $rdfHelpers;
        $this->statementFactory = $statementFactory;
        $this->graphs = $graphs;
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
     * @param string $uri
     * @return ResourceGuy
     */
    public function createInstanceByUri(string $uri) : ResourceGuy
    {
        $res = $this->store->query(
            'SELECT * '. $this->buildGraphsList($this->graphs) .' WHERE { <'. $uri .'> ?p ?o.}'
        );

        $guy = new ResourceGuy($this->commonNamespaces);

        $guy['_idUri'] = $this->nodeFactory->createNamedNode($uri);

        foreach ($res as $entry) {
            $predicateUri = $entry['p']->getUri();
            if (isset($guy[$predicateUri])) {
                $guy[$predicateUri] = array($guy[$predicateUri], $entry['o']);
            } else {
                $guy[$predicateUri] = $entry['o'];
            }
        }

        return $guy;
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

        // literal
        } else {
            return $this->nodeFactory->createLiteral($value);
        }
    }

    /**
     * @return array
     */
    public function toStatements(ResourceGuy $guy) : array
    {
        $statements = array();

        if (false == isset($guy['_idUri'])) {
            throw new KnorkeException('No _idUri key defined.');
        }

        $guyNode = $guy['_idUri'];

        foreach ($guy as $property => $object) {
            if ('_idUri' == $property) {
                continue;
            }

            if (false === is_array($object)) {
                $objects = array($object);
            } else {
                $objects = $object;
            }

            foreach ($objects as $entry) {
                if ($entry instanceof Node) {
                    $statements[] = $this->statementFactory->createStatement(
                        $guyNode,
                        $this->nodeFactory->createNamedNode($this->commonNamespaces->extendUri($property)),
                        $entry
                    );

                } elseif ($entry instanceof ResourceGuy) {
                    // connect both guys
                    $statements[] = $this->statementFactory->createStatement(
                        $guyNode,
                        $this->nodeFactory->createNamedNode($this->commonNamespaces->extendUri($property)),
                        $entry['_idUri']
                    );

                    $statements = array_merge($statements, $this->toStatements($entry));

                } else {
                    throw new KnorkeException('Unknown object type: '. json_encode($object));
                }
            }
        }

        return $statements;
    }
}
