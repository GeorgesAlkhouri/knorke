<?php

namespace Knorke;

use Knorke\Rdf\HashableStatementImpl;
use Saft\Rdf\RdfHelpers;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\NodeFactory;
use Saft\Rdf\StatementFactory;
use Saft\Store\Store;

/**
 * This class provides functions to create a diff between 2 sets of statements (triple). It basically
 * shows which statements are unique to which set.
 */
class TripleDiff
{
    protected $commonNamespaces;
    protected $nodeFactory;
    protected $rdfHelpers;
    protected $statementFactory;
    protected $store;

    /**
     * @param RdfHelpers $rdfHelpers
     * @param CommonNamespaces $commonNamespaces
     * @param NodeFactory $nodeFactory
     * @param StatementFactory $statementFactory
     * @param Store $store
     */
    public function __construct(
        RdfHelpers $rdfHelpers,
        CommonNamespaces $commonNamespaces,
        NodeFactory $nodeFactory,
        StatementFactory $statementFactory,
        Store $store
    ) {
        $this->rdfHelpers = $rdfHelpers;
        $this->commonNamespaces = $commonNamespaces;
        $this->nodeFactory = $nodeFactory;
        $this->statementFactory = $statementFactory;
        $this->store = $store;
    }

    /**
     * Computes the diff of two graphs of a store.
     *
     * @param string $graphUri1 URI of the first graph.
     * @param string $graphUri2 URI of the second graph to check against.
     * @return array Array of 2 elements: first one contains all statements which are unique to
     *               the first graph, second one contains all statements which are unique to the
     *               second graph.
     */
    public function computeDiffForTwoGraphs(string $graphUri1, string $graphUri2) : array
    {
        /*
         * hint 1: how to query the store
         */
        $result = $this->store->query('
            SELECT * FROM <http://knorke/testgraph/2> WHERE {
                ?s ?p ?o.
            }
        ');
        // result is an SetResult instance and contains Statement instances,
        // each containing used variables and referencing Node instances. A SetResult acts like an array.
        // FYI: https://github.com/SaftIng/Saft/blob/master/src/Saft/Sparql/Result/SetResultImpl.php

        // hint 2: use commonNamespaces->getUri('rdf') to get the URI for the rdf prefix
        // hint 3: use commonNamespaces->extendUri('rdf:type') to replace prefix with full URI, if available
        // hint 4: use commonNamespaces->shortenUri('http://...') to replace URI with prefix, if available
        // FYI: https://github.com/SaftIng/Saft/blob/master/src/Saft/Rdf/CommonNamespaces.php

        // TODO @Georges
        return array(
            array(),
            array()
        );
    }

    /**
     * Computes the diff of two sets containing triples (Statement instances).
     *
     * @param array $statementSet1
     * @param array $statementSet2
     * @return array Array of 2 elements: first one contains all statements which are unique to
     *               the first set, second one contains all statements which are unique to the
     *               second set.
     */
    public function computeDiffForTwoTripleSets(array $statementSet1, array $statementSet2) : array
    {
        // hint: a Statement is either a triple or quad and contains at least 3 Node instances, max is 4.
        //       For more info look into https://github.com/SaftIng/Saft/tree/master/src/Saft/Rdf and search for
        //       NamedNodeImpl, LiteralImpl and BlankNodeImpl.

        // TODO @Georges
        //
        // Optional: Check for blank_nodes and aborte if necessary

        $reduceFunction = function($carry , $item)
        {
            $hash = $item->hash();
            $carry[$hash] = $item;

            return $carry;
        };

        // Nice benefit: hash set does not contain duplicates
        $hashset1 = array_reduce($statementSet1, $reduceFunction, []);
        $hashset2 = array_reduce($statementSet2, $reduceFunction, []);

        $inBoth = array_intersect(array_keys($hashset1), array_keys($hashset2));
        $diffSet1 = array_diff(array_keys($hashset1), array_keys($hashset2));
        $diffSet2 = array_diff(array_keys($hashset2), array_keys($hashset1));
        
        return array(
            $inBoth,
            $diffSet1,
            $diffSet2
        );
    }
}
