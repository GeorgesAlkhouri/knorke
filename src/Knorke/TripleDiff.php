<?php

namespace Knorke;

use Saft\Rdf\RdfHelpers;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\NodeFactory;
use Saft\Rdf\StatementFactory;

/**
 * This class provides functions to create a diff between 2 sets of statements (triple). It basically
 * shows which statements are unique to which set.
 */
class TripleDiff
{
    protected $rdfHelpers;
    protected $commonNamespaces;
    protected $nodeFactory;
    protected $statementFactory;

    /**
     * @param RdfHelpers $rdfHelpers
     * @param CommonNamespaces $commonNamespaces
     * @param NodeFactory $nodeFactory
     * @param StatementFactory $statementFactory
     */
    public function __construct(
        RdfHelpers $rdfHelpers,
        CommonNamespaces $commonNamespaces,
        NodeFactory $nodeFactory,
        StatementFactory $statementFactory
    ) {
        $this->rdfHelpers = $rdfHelpers;
        $this->commonNamespaces = $commonNamespaces;
        $this->nodeFactory = $nodeFactory;
        $this->statementFactory = $statementFactory;
    }

    /**
     * @param array $statementSet1
     * @param array $statementSet2
     * @return array Array of 2 elements: first one contains all statements which are unique to the first set,
     *               second one contains all statements which are unique to the second set.
     */
    public function computeDiff(array $statementSet1, array $statementSet2) : array
    {
        // TODO @Georges
        return array(
            array(),
            array()
        );
    }
}
