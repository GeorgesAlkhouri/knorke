<?php

namespace Knorke\Store\QueryHandler;

use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\Node;
use Saft\Rdf\NodeFactory;
use Saft\Sparql\Result\Result;

abstract class AbstractQueryHandler
{
    protected $commonNamespaces;
    protected $nodeFactory;

    public function __construct(CommonNamespaces $commonNamespaces, NodeFactory $nodeFactory)
    {
        $this->commonNamespaces = $commonNamespaces;
        $this->nodeFactory = $nodeFactory;
    }

    /**
     * @param array $entries
     * @param array $filterInformation Default: null
     * @return array
     */
    protected function applyFilters(array $entries, $filterInformation = null)
    {
        /*
         * apply filter like FILTER (?p = <http://...>) and remove statements which dont match
         */
        if (null !== $filterInformation) {
            foreach ($entries as $key => $stmtArray) {
                // remove entries which are not fit the given filters
                $relatedNode = $stmtArray[$filterInformation['variable_letter']];
                $relatedNodeUri = $relatedNode->getUri();
                // we assume that the node is a named node
                if (false == isset($filterInformation['possible_values'][$relatedNodeUri])) {
                    // if its node does not match with the filter requirements,
                    // remove the statement from the result
                    unset($entries[$key]);
                }
            }
        }

        return $entries;
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
                $value = $node->getBlankId();
            } else {
                throw new \Exception('Unknown Node type given');
            }

        } else { // anypattern
            $value = (string)$node;
        }

        return $value;
    }

    /**
     * @param array $statementsPerGraph
     * @param array $triplePattern
     * @param array $filterInformation Default: null
     * @return Result
     */
    abstract public function handle(
        array $statementsPerGraph,
        array $triplePattern,
        array $filterInformation = null
    ) : Result;
}
