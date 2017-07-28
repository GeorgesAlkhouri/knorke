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
    protected function buildGraphsList(array $graphUris) : string
    {
        $fromGraphList = array();

        foreach ($graphUris as $graph) {
            $fromGraphList[] = 'FROM <'. $graph . '>';
        }

        return implode(' ', $fromGraphList);
    }

    /**
     * Just creates a new instance of ResourceGuy.
     *
     * @return ResourceGuy
     */
    public function createEmptyInstance()
    {
        return new ResourceGuy($this->commonNamespaces);
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

        $uri = $this->commonNamespaces->extendUri($uri);

        $res = $this->store->query(
            'SELECT * '. $this->buildGraphsList($this->graphs) .' WHERE { <'. $uri .'> ?p ?o.}'
        );

        $guy = new ResourceGuy($this->commonNamespaces);

        $guy['_idUri'] = $this->nodeFactory->createNamedNode($uri);

        foreach ($res as $entry) {
            $predicateUri = $entry['p']->getUri();

            // if maxLevel higher 1 was given, transform NamedNode instances to ResourceGuy instances, recursivly
            if (1 < $maxLevel && $currentLevel <= $maxLevel && $entry['o'] instanceof NamedNode) {
                $res = $this->createInstanceByUri(
                    $entry['o']->getUri(),
                    $maxLevel,
                    $currentLevel+1
                );

                // check before saving, because sometimes null gets returned. that can
                // happen if referenced URI has no further triples. in that case we
                // will keep the URI.
                if ($res instanceof ResourceGuy) {
                    $entry['o'] = $res;
                } else {
                    $entry['o'] = $entry['o'];
                }
            }

            if (isset($guy[$predicateUri])) {
                // if array, extend it
                if (is_array($guy[$predicateUri])) {
                    $arr = $guy[$predicateUri];
                    $arr[] = $entry['o'];
                    $guy[$predicateUri] = $arr;
                // otherwise create array
                } else {
                    $guy[$predicateUri] = array($guy[$predicateUri], $entry['o']);
                }
            } else {
                $guy[$predicateUri] = $entry['o'];
            }
        }

        return $guy;
    }

    /**
     * @param string $typeUri
     * @param int $maxLevel Optional, default is 1
     */
    public function getInstancesByType(string $typeUri, int $maxLevel = 1) : array
    {
        $typeUri = $this->commonNamespaces->extendUri($typeUri);

        $res = $this->store->query('
            PREFIX rdf: <'. $this->commonNamespaces->getUri('rdf') .'>
            SELECT * '. $this->buildGraphsList($this->graphs) .' WHERE { ?guy rdf:type <'. $typeUri .'>. }'
        );

        $guys = array();

        foreach ($res as $entry) {
            $guys[] = $this->createInstanceByUri($entry['guy'], $maxLevel);
        }

        return $guys;
    }

    /**
     * @param string $typeUri
     * @param string $whereClause
     * @param int $maxLevel Optional, default is 1
     */
    public function getInstancesByWhereClause(
        string $typeUri,
        string $whereClause,
        int $maxLevel = 1
    ) : array {
        $typeUri = $this->commonNamespaces->extendUri($typeUri);

        $res = $this->store->query('
            PREFIX rdf: <'. $this->commonNamespaces->getUri('rdf') .'>
            SELECT * '. $this->buildGraphsList($this->graphs) .' WHERE {
                ?guy rdf:type <'. $typeUri .'>.
                '. $whereClause .'
            }'
        );

        $guys = array();

        foreach ($res as $entry) {
            $guys[] = $this->createInstanceByUri($entry['guy'], $maxLevel);
        }

        return $guys;
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
