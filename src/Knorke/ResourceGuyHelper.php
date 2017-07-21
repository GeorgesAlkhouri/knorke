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
     * @param int $level Optional, default is 1. If higher 1, on each level all NamedNode instances get
     *                   replaced by ResourceGuy instance, if available.
     * @param int $currentLevel Optional, default is 1. Stores current level. Only relevant when in recursion.
     * @return ResourceGuy|null
     */
    public function createInstanceByUri(string $uri, int $maxLevel = 1, int $currentLevel = 1)
    {
        if ($maxLevel < $currentLevel) {
            return null;
        }

        $res = $this->store->query(
            'SELECT * '. $this->buildGraphsList($this->graphs) .' WHERE { <'. $uri .'> ?p ?o.}'
        );

        $guy = new ResourceGuy($this->commonNamespaces);

        $guy['_idUri'] = $this->nodeFactory->createNamedNode($uri);

        foreach ($res as $entry) {
            $predicateUri = $entry['p']->getUri();
            if (isset($guy[$predicateUri])) {
                $guy[$predicateUri] = array($guy[$predicateUri], $entry['o']);

            } elseif ($entry['o'] instanceof NamedNode) {
                // if maxLevel higher 1 was given, transform NamedNode instances to ResourceGuy instances, recursivly
                if (1 < $maxLevel && $currentLevel <= $maxLevel) {
                    $res = $this->createInstanceByUri(
                        $entry['o']->getUri(),
                        $maxLevel,
                        $currentLevel+1
                    );
                    // check before saving, because sometimes null gets returned. that can
                    // happen if referenced URI has no further triples. in that case we
                    // will keep the URI.
                    if ($res instanceof ResourceGuy) {
                        $guy[$predicateUri] = $res;
                    } else {
                        $guy[$predicateUri] = $entry['o'];
                    }

                } elseif (1 == $maxLevel || $currentLevel == $maxLevel) {
                    $guy[$predicateUri] = $entry['o'];
                }
            } else {
                $guy[$predicateUri] = $entry['o'];
            }
        }

        return $guy;
    }

    /**
     * Transforms a ResourceGuy instance and potential referenced ones to statement array.
     *
     * @return array Array of Statement instances.
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
